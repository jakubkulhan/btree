<?php
require_once dirname(__FILE__) . '/../btree.php';

// open B+Tree; file does not have to exist
$btree = btree::open('my.tree'); 

// btree::open() returns false if anything goes wrong
if ($btree === FALSE) die('cannot open'); 

// sets key 'key' to value 'value'
$btree->set('key', 'value');
// value can be arbitrary PHP value (string, integer, boolean, array...)
// => anything that can be searialized through serialize()

// gets value under key 'key'
$value = $btree->get('key');

// check value
assert($btree->get('key') === 'value');

// no cleanup needed!

// that's all you need to manipulate with this efficient key-value storage ;-)
