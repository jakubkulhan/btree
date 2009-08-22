<?php
final class btree
{
    /**
     * Size of header
     */
    const SIZEOF_HEADER = 6;

    /**
     * Header that has to be at end of every file
     */
    const HEADER = "\xffbtree";

    /**
     * Maximum number of keys per node (do not even think about to change it)
     */
    const NODE_SLOTS = 16;

    /**
     * Size of integer (pack type N)
     */
    const SIZEOF_INT = 4;

    /**
     * This is key-value node
     */
    const KVNODE = 'kv';

    /**
     * This is key-pointer node
     */
    const KPNODE = 'kp';

    /**
     * BTree file handle
     */
    private $handle;

    /**
     * Use static method open() to get instance
     */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Free resources
     */
    public function __descruct()
    {
        fclose($this->handle);
    }

    /**
     * Get value by key
     * @param string
     * @return mixed
     */
    public function get($key)
    {
        $lookup = $this->lookup($key);
        $leaf = end($lookup);
        if ($leaf !== NULL && isset($leaf[$key])) return $leaf[$key];
        return NULL;
    }

    /**
     * Set value under given key
     * @param string
     * @param mixed NULL deletes given key
     * @return bool
     */
    public function set($key, $value)
    {
        // lock
        if (!flock($this->handle, LOCK_EX)) return FALSE;
        if (fseek($this->handle, 0, SEEK_END) === -1) {
            flock($this->handle, LOCK_UN);
            return FALSE;
        }
        if (($pos = ftell($this->handle)) === FALSE) {
            flock($this->handle, LOCK_UN);
            return FALSE;
        }
        $cursor = $pos;

        // key lookup
        $lookup = $this->lookup($key);
        $node = array_pop($lookup);
        if ($node === NULL) return FALSE;

        // change value
        $index = current(array_keys($node));
        $node_type = self::KVNODE;
        $new_index = NULL;
        if ($value === NULL) unset($node[$key]);
        else $node[$key] = $value;

        // traverse tree up
        do {
            if (count($node) <= intval(self::NODE_SLOTS / 2) && !empty($lookup)) {
                $upnode = (array) array_pop($lookup);
                $new_index = current(array_keys($upnode));
                $sibling = $prev = array(NULL, NULL);

                foreach ($upnode as $k => $v) {
                    if ($index === $k) $sibling = $prev; // left sibling
                    else if ($index === $prev[0]) $sibling = array($k, $v); // right sibling

                    if ($sibling[0] !== NULL) {
                        list($sibling_type, $sibling_node) = $this->node($sibling[1]);
                        if ($sibling_type === NULL || $sibling_node === NULL) {
                            ftruncate($this->handle, $pos);
                            flock($this->handle, LOCK_UN);
                            return FALSE;
                        }
                        $node = array_merge($node, $sibling_node);
                        unset($upnode[$sibling[0]]);
                    }

                    $prev = array($k, $v);
                    $sibling = array(NULL, NULL);
                }

                array_push($lookup, $upnode);
            }

            ksort($node);
            if (count($node) <= self::NODE_SLOTS) $nodes = array($node);
            else $nodes = array_chunk($node, ceil(count($node) / ceil(count($node) / self::NODE_SLOTS)), TRUE);

            $upnode = array_merge(array(), (array) array_pop($lookup));
            if ($new_index === NULL) $new_index = current(array_keys($upnode));
            unset($upnode[$index]);

            foreach ($nodes as $_) {
                $serialized = self::serialize($node_type, $_);
                $to_write = pack('N', strlen($serialized)) . $serialized;
                if (fwrite($this->handle, $to_write, strlen($to_write)) !== strlen($to_write)) {
                    ftruncate($this->handle, $pos);
                    flock($this->handle, LOCK_UN);
                    return FALSE;
                }
                $upnode[current(array_keys($_))] = $cursor;
                $cursor += strlen($to_write);
            }

            $node_type = self::KPNODE;
            $index = $new_index;
            $new_index = NULL;

            if (count($upnode) <= 1) {
                $root = current(array_values($upnode));
                break;
            } else array_push($lookup, $upnode);

        } while (($node = array_pop($lookup)));

        // write root
        if (!(fflush($this->handle) &&
            fwrite($this->handle, pack('N', $root) . self::HEADER) 
                === self::SIZEOF_HEADER + self::SIZEOF_INT &&
            fflush($this->handle))) 
        {
            ftruncate($this->handle, $pos);
            flock($this->handle, LOCK_UN);
            return FALSE;
        }

        flock($this->handle, LOCK_UN);

        return TRUE;
    }

    /**
     * Look up key
     * @param string
     * @param string
     * @param array
     * @return array traversed nodes
     */
    private function lookup($key, $node_type = NULL, $node = NULL)
    {
        if ($node_type === NULL || $node === NULL) list($node_type, $node) = $this->root();
        if ($node_type === NULL || $node === NULL) return array(NULL);
        return array_merge(array($node), $this->{'lookup' . $node_type}($key, $node));
    }

    private function lookupkv($key, $node)
    {
        return array();
    }

    private function lookupkp($key, $node)
    {
        $keys = array_keys($node);
        $l = 0;
        $r = count($keys);

        while ($l < $r) {
            $i = $l + intval(($r - $l) / 2);
            if (strcmp($keys[$i], $key) < 0) $l = $i + 1;
            else $r = $i;
        }

        $l = max(0, $l + ($l >= count($keys) ? -1 : (strcmp($keys[$l], $key) <= 0 ? 0 : -1)));

        list($child_type, $child) = $this->node($node[$keys[$l]]);
        if ($child_type === NULL || $child === NULL) return NULL;

        $children = $this->lookup($key, $child_type, $child);
        if ($children === NULL) return NULL;

        return $children;
    }

    /**
     * Get root node
     * @return array 0 => node type, 1 => node; array(NULL, NULL) on failure
     */
    private function root()
    {
        // try EOF
        if (fseek($this->handle, -(self::SIZEOF_HEADER + self::SIZEOF_INT), SEEK_END) 
            === -1) return array(NULL, NULL);

        if (strlen($data = fread($this->handle, self::SIZEOF_INT + self::SIZEOF_HEADER)) 
            !== self::SIZEOF_INT + self::SIZEOF_HEADER) return array(NULL, NULL);

        $header = substr($data, self::SIZEOF_INT);
        $root = substr($data, 0, self::SIZEOF_INT);

        // header-hunting
        if (substr($data, self::SIZEOF_INT) !== self::HEADER) {
            $root = NULL;

            if (($size = ftell($this->handle)) === FALSE) return array(NULL, NULL);
            for ($i = -1; ($off = $i * 128) + $size > 128; --$i) {
                if (fseek($this->handle, $off, SEEK_END) === -1) return array(NULL, NULL);
                $data = fread($this->handle, 256);
                if (($pos = strrpos($data, self::HEADER)) !== FALSE) {
                    if ($pos === 0) continue;
                    $root = substr($data, $pos - 4, 4);
                    break;
                }
            }

            if ($root === NULL) return array(NULL, NULL);
        }

        // get root node
        list(,$p) = unpack('N', $root);
        return $this->node($p);
    }

    /**
     * Get node
     * @param int pointer to node (offset in file)
     * @retrun array 0 => node type, 1 => node; array(NULL, NULL) on failure
     */
    private function node($p)
    {
        if (fseek($this->handle, $p, SEEK_SET) === -1) return array(NULL, NULL);
        if (strlen($data = fread($this->handle, self::SIZEOF_INT))
            !== self::SIZEOF_INT) return array(NULL, NULL);
        list(,$n) = unpack('N', $data);
        if (strlen($node = fread($this->handle, $n)) !== $n) return array(NULL, NULL);
        return self::unserialize($node);
    }

    /**
     * Open/create new btree
     */
    public static function open($file)
    {
        if (!($handle = @fopen($file, 'a+b'))) return FALSE;

        // write default node if neccessary
        if (fseek($handle, 0, SEEK_END) === -1) {
            fclose($handle);
            return FALSE;
        }
        if (ftell($handle) === 0) {
            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                return FALSE;
            }
            $root = self::serialize(self::KVNODE, array());
            $to_write = pack('N', strlen($root)) . $root;
            if (fwrite($handle, $to_write, strlen($to_write)) !== strlen($to_write) ||
                !self::header($handle, 0) || !flock($handle, LOCK_UN)) 
            {
                ftruncate($handle, 0);
                fclose($handle);
                return FALSE;
            }
        }

        // create instance
        return new self($handle);
    }

    /**
     * Serialize node
     * @param string node type
     * @param array node
     * @return string
     */
    private static function serialize($type, array $node)
    {
        return $type . serialize($node);
    }

    /**
     * Unserialize node
     * @param string node type
     * @param string serialized node
     * @return array
     */
    private static function unserialize($str)
    {
        return array(substr($str, 0, 2), unserialize(substr($str, 2)));
    }

    /**
     * Writes header to file
     * @param resource file handle
     * @param int root position
     * @return bool
     */
    private static function header($handle, $root)
    {
        $to_write = pack('N', $root) . self::HEADER;
        return fwrite($handle, $to_write, strlen($to_write)) === strlen($to_write);
    }
}
