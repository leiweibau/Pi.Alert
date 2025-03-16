<?php
echo '<h1>Languages</h1>';

require './de_de.php';
$dede = $pia_lang;
echo sizeof($pia_lang);
echo '<br>';

require './en_us.php';
$enus = $pia_lang;
echo sizeof($pia_lang);
echo '<br>';

require './es_es.php';
$eses = $pia_lang;
echo sizeof($pia_lang);
echo '<br>';

require './fr_fr.php';
$frfr = $pia_lang;
echo sizeof($pia_lang);
echo '<br>';

require './it_it.php';
$itit = $pia_lang;
echo sizeof($pia_lang);
echo '<br>';
?>