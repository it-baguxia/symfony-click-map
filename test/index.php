<?php
/**
 * Exemple d'utilisation de la classe HeatmapFromDatabase
 * Use example of HeatmapFromDatabase class
 * 
 * Table utilisée pour ce test / Used table for this test:

CREATE TABLE `CLICKS` (
  `CLICK_X` smallint(5) unsigned NOT NULL,
  `CLICK_Y` smallint(5) unsigned NOT NULL,
  KEY `CLICK_Y` (`CLICK_Y`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `CLICKS` (`CLICK_X`, `CLICK_Y`) VALUES (10, 10),(20, 20),(30, 30),(40, 40),(50, 50),(60, 60),(70, 70),(80, 80),(90, 90),(100, 100),(110, 110),(120, 120),(130, 130),(140, 140),(150, 150),(160, 160),(170, 170),(180, 180),(190, 190);

**/
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>ClickHeat | Examples</title>
</head>
<body>
<?php
//exit('Enlevez cette ligne pour que ce fichier marche/Remove this line to make this file work');

include 'utils/Heatmap.class.php';

$heatmap = new Heatmap();
/**
 * Requête (CLICK_Y se doit d'avoir un index pour de bonnes performances, voir EXPLAIN dans le manuel MySQL)
 * The query (CLICK_Y should have an index for good performances, see EXPLAIN in MySQL manual)
**/


$heatmap->database = 'symfony-click-map';
$heatmap->user = 'clickmap';
$heatmap->password = 'clickmap';
/** Fichiers temporaires / Temporary files */
$heatmap->cache = '.';
/** Fichiers générés / Generated files */
$heatmap->path = '.';
/** Fichier final / Final file */
$heatmap->file = 'resultfromdb.png';

$heatmap->alpha = 0;

/**
 * 设定宽度和高度，不由计算生成
 */
$heatmap->width  = 200;
$heatmap->height = 200;
//$heatmap->palette = true;


/**
 * On force la hauteur finale (attention à la consommation mémoire dans ce cas !)
 * Forcing final height (take care of the memory consumption in such case!)
**/
$heatmap->generate(200, 100);


echo 'Résultats/Results: ';

echo '<pre>';
echo '</pre>';

echo '<br /><br /><p style="line-height:0;">';

echo '<img src="', $heatmap->file, '" width="', $heatmap->width, '" height="', $heatmap->height, '" alt="" /> Image ', '<br /><br /><br /><br />';

echo '</p>';

?>
</body>
</html>