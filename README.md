# btree

Append-only B+Tree implemented purely in PHP. Intended to be an efficient key-value storage. B+Tree is a very popular in storage systems (databases) because of its fast retrieval even with millions of records.

## API

Get the instance:

    $btree = btree::open('my.tree');

`btree::open()` takes one argument – path to file with B+Tree. If file does not exists, it is created for you. `btree::open()` returns `FALSE` in case that anything went wrong, newly created otherwise.

Set (insert, update) a value for some key:

    $btree->set('key', 'value');

`set()` returns `FALSE` on failure, `TRUE` otherwise.

Get the value:

    $btree->get('key');

If key does not exist or anything goes wrong, `get()` returns `NULL`. Value under the key is returned otherwise.

Delete key:

    $btree->set('key', NULL);

`NULL`s (deletes) the key. Same return values as in case of inserting/updating value.

That is all you need.

## Advanced

Need all values? Get pointers to all leaf nodes:

    $leaves = $btree->leaves();

And then process nodes:

    $values = array();
    foreach ($leaves as $p) {
        list(,$leaf) = $btree->node($leaf);
        $values += $leaf;
    }

Does your B+Tree consume a lot of space? Then compact it:

    $btree->compact();

## License

The MIT license:

    Copyright (c) 2009 Jakub Kulhan <jakub.kulhan@gmail.com>

    Permission is hereby granted, free of charge, to any person
    obtaining a copy of this software and associated documentation
    files (the "Software"), to deal in the Software without
    restriction, including without limitation the rights to use,
    copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following
    conditions:

    The above copyright notice and this permission notice shall be
    included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
    EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
    OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
    NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
    HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
    WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
    OTHER DEALINGS IN THE SOFTWARE.
