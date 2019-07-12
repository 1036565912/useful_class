<?php
/**
 * Created by PhpStorm.
 * User: 10365
 * Date: 2019/7/12
 * Time: 11:15
 */


require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Table;


$word_content = IOFactory::load('./test_doc_json.docx','Word2007')->getSections();

foreach ($word_content as $s) {
    $elements = $s->getElements();
    foreach ($elements as $element) {
        //元素中 只有文本和表格
        $class_name = get_class($element) ;
        var_dump($class_name);
        echo "<br />----------------父元素-----------------<br />";
        if ($class_name == 'PhpOffice\\PhpWord\\Element\\TextRun') {
            $e = $element->getElements();
            foreach ($e as $inE) {
                $ns = get_class($inE);
                $elName = explode('\\',$ns)[3];
                var_dump($elName);
                echo "<br />------------------------子元素------------------------<br />";

                if($elName == 'Text') {
                    var_dump($inE->getText());
                    echo '<br />-----------------------<br />';
                } elseif ($elName == 'TextBreak'){
                    var_dump('分隔符');
                }

            }
        }

    }
}
