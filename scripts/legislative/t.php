<?php
$dirs = file('house');
foreach($dirs as $dir) {
    $new_folder = str_replace('senate','house', trim($dir));
    echo 'cp -R '.trim($dir).' '. trim($new_folder) ."\n";
}
