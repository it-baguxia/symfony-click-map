<?php

/**
 * ClickHeat: Classe de génération des cartes / Maps generation class
 * 
 * Cette classe est VOLONTAIREMENT écrite pour PHP 4
 * This class is VOLUNTARILY written for PHP 4
 * 
 * @author Yvan Taviaud - LabsMedia - www.labsmedia.com
 * @since 08/05/2007
 * */
class Heatmap
{
	/* @var integer $memory Limite de mémoire / Memory limit */

	var $memory = 8388608;
	/* @var integer $step Groupement des pixels / Pixels grouping */
	var $step = 5;
	/* @var integer $startStep */
	var $startStep;
	/* @var integer $dot Taille des points de chaleur / Heat dots size */
	var $dot = 19;
	
	/* @var boolean $heatmap Affichage sous forme de carte de température / Show as heatmap */
	var $heatmap = true;
	
	
	/* @var boolean $palette Correctif pour la gestion de palette (cas des carrés rouges) / Correction for palette (in case of red squares) */
	var $palette = false;
	
	/* @var boolean $alpha Valeur de transparence de l'image (par défaut pas de transparence) / Alpha level (default is no alpha) */
	var $alpha = 0;
	
	/* @var boolean $rainbow Affichage de l'arc-en-ciel / Show rainbow */
	var $rainbow = true;
	/* @var boolean $copyleft Affichage du copyleft / Show copyleft */
	var $copyleft = true;
	/* @var integer $width Largeur de l'image / Image width */
	var $width;
	/* @var integer $height Hauteur de l'image / Image height */
	var $height;
	/* @var integer $maxClicks Nombre de clics maximum (sur 1 pixel) / Maximum clicks (on 1 pixel) */
	var $maxClicks;
	/* @var integer $maxY Hauteur maximale (point le plus bas) / Maximum height (lowest point) */
	var $maxY;
	/* @var resource $image Ressource image / Image resource */
	var $image;
	/* @var string $file Nom du fichier image (incluant le %d) / Image filename (including %d) */
	var $file;
	/* @var string $path Chemin du fichier image / Image path */
	var $path;
	/* @var string $cache Chemin du cache / Cache path */
	var $cache;
	/* @var string $error Erreur / Error */
	var $error = '';
	/* @var array $__colors Niveaux de dégradé (de 0 à 127) / Gradient levels (from 0 to 127) */
	var $__colors = array(50, 70, 90, 110, 120);
	/* @var integer $__low Niveau minimal de couleur RVB / Lower RGB level of color */
	var $__low = 0;
	/* @var integer $__high Niveau maximal de couleur RVB / Higher RGB level of color */
	var $__high = 255;
	/* @var integer $__grey Niveau du gris (couleur du 0 clic) / Grey level (color of no-click) */
	var $__grey = 240;

	
	//从数据库类中分离的变量
	/** @var string $host Hôte (serveur) MySQL / MySQL host (server) */
	var $host = 'localhost';
	/** @var string $user Utilisateur MySQL / MySQL user */
	var $user = '';
	/** @var string $password Mot de passe de l'utilisateur MySQL / MySQL user's password */
	var $password = '';
	/** @var string $database Nom de la base de données MySQL / MySQL database name */
	var $database = '';
	/** @var integer $limit Limite du nombre de résultats renvoyés par la requête à chaque appel / Maximum number of results returned by each request call */
	var $limit = 1000;
	/** @var resource $link Lien (interne) MySQL / MySQL (internal) link */
	var $link = false;
	/** @var string $query Requête renvoyant les coordonnées des clics / Clicks coordinates query */
	var $query = 'SELECT COORDS_X, COORDS_Y FROM CLICKS WHERE COORDS_Y BETWEEN %d AND %d';
	/** @var string $maxQuery Requête renvoyant la coordonnées Y maximale / Max Y coordinate query */
	var $maxQuery = 'SELECT MAX(COORDS_Y) FROM CLICKS';
	
	
	
	function Heatmap()
	{
		$this->alpha = min($this->alpha, 127);
	}

	/**
	 * 检查文件夹是否符合合法性
	 */
	private function checkFolderPath(){
		
		/* First check paths */
		$this->path  = rtrim($this->path, '/').'/';
		$this->cache = rtrim($this->cache, '/').'/';
		$this->file = str_replace('/', '', $this->file);
		if (!is_dir($this->path) || $this->path === '/')
		{
			return $this->raiseError('path = "'.$this->path.'" is not a directory or is "/"');
		}
		if (!is_dir($this->cache) || $this->cache === '/')
		{
			return $this->raiseError('cache = "'.$this->cache.'" is not a directory or is "/"');
		}
		if (strpos($this->file, '%d') === false)
		{
			return $this->raiseError('file = "'.$this->file.'" doesn\'t include a \'%d\' for image number');
		}
		
		
	}//function checkFolderPath() end
	
	
	function generate($width, $height = 0)
	{
		$this->checkFolderPath();

		$files = array('filenames' => array(), 'absolutes' => array()); /* Generated files list */
		$this->startStep = (int) floor(($this->step - 1) / 2);
		$nbOfImages = 1; /* Will be modified after the first image is created */
		$this->maxClicks = 1; /* Must not be zero for divisions */
		$this->maxY = 0;
		
		
		/* Startup tasks */
		if ($this->startDrawing() === false)
		{
			return false;
		}
		$files['width'] = $this->width;
		$files['height'] = $this->height;
		
		
		for ($image = 0; $image < $nbOfImages; $image++)
		{
			/* Image creation */
			$this->image = imagecreatetruecolor($this->width, $this->height);
			/* Image is filled in the color "0", which means 0 click */
			
			imagefill($this->image, 0, 0, 0);
			

			/* Draw next pixels for this image */
			if ($this->drawPixels($image) === false)
			{
				return false;
			}
			if ($image === 0)
			{
				if ($this->maxY === 0)
				{
					if (defined('LANG_ERROR_DATA') === true)
					{
						return $this->raiseError(LANG_ERROR_DATA);
					}
					else
					{
						$this->maxY = 1;
					}
				}
				$nbOfImages = (int) ceil($this->maxY / $this->height);
				$files['count'] = $nbOfImages;
			}

			imagepng($this->image, sprintf($this->cache.$this->file.'_temp', $image));
			
			imagedestroy($this->image);

			/* Result files */
			$files['filenames'][] = sprintf($this->file, $image);
			$files['absolutes'][] = sprintf($this->path.$this->file, $image);
		}
		/* End tasks */
		if ($this->finishDrawing() === false)
		{
			return false;
		}

		/* Now, our image is a direct representation of the clicks on each pixel, so create some fuzzy dots to put a nice blur effect if user asked for a heatmap */
		for ($i = 0; $i < 128; $i++)
		{
			$dots[$i] = imagecreatetruecolor($this->dot, $this->dot);
			imagealphablending($dots[$i], false);
		}
		
		for ($x = 0; $x < $this->dot; $x++)
		{
			for ($y = 0; $y < $this->dot; $y++)
			{
				$sinX = sin($x * pi() / $this->dot);
				$sinY = sin($y * pi() / $this->dot);
				for ($i = 0; $i < 128; $i++)
				{
					$alpha = 127 - $i * $sinX * $sinY * $sinX * $sinY;
					imagesetpixel($dots[$i], $x, $y, ((int) $alpha) * 16777216);
				}
			}
		}

		
		$colors = $this->createColors();
		
		for ($image = 0; $image < $nbOfImages; $image++)
		{
			$img = imagecreatetruecolor($this->width, $this->height);
			$white = imagecolorallocate($img, 255, 255, 255);
			/* «imagefill» doesn't work correctly on some hosts, ending on a red drawing */
			imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $white);
			imagealphablending($img, true);

			$imgSrc = @imagecreatefrompng(sprintf($this->cache.$this->file.'_temp', $image));
			@unlink(sprintf($this->cache.$this->file.'_temp', $image));
			if ($imgSrc === false)
			{
				return $this->raiseError('::MEMORY_OVERFLOW::');
			}
			for ($x = $this->startStep; $x < $this->width; $x += $this->step)
			{
				for ($y = $this->startStep; $y < $this->height; $y += $this->step)
				{
					$dot = (int) ceil(imagecolorat($imgSrc, $x, $y) / $this->maxClicks * 100);
					if ($dot !== 0)
					{
						imagecopy($img, $dots[$dot], ceil($x - $this->dot / 2), ceil($y - $this->dot / 2), 0, 0, $this->dot, $this->dot);
					}
				}
			}
			/* Destroy image source */
			imagedestroy($imgSrc);

			/* Rainbow */
			if ($image === 0 && $this->rainbow === true)
			{
				for ($i = 1; $i < 128; $i += 2)
				{
					/* Erase previous alpha channel so that clicks don't change the heatmap by combining their alpha */
					imageline($img, ceil($i / 2), 0, ceil($i / 2), 10, 16777215);
					/* Then put our alpha */
					imageline($img, ceil($i / 2), 0, ceil($i / 2), 10, (127 - $i) * 16777216);
				}
			}

			if ($this->alpha === 0)
			{
				/* Some version of imagetruecolortopalette() don't transform alpha value to non alpha */
				if ($this->palette === true)
				{
					for ($x = 0; $x < $this->width; $x++)
					{
						for ($y = 0; $y < $this->height; $y++)
						{
							/* Get Alpha value (0->127) and transform it to red (divide color by 16777216 and multiply by 65536 * 2 (red is 0->255), so divide it by 128) */
							imagesetpixel($img, $x, $y, (imagecolorat($img, $x, $y) & 0x7F000000) / 128);
						}
					}
				}

				/* Change true color image to palette then change palette colors */
				imagetruecolortopalette($img, false, 127);
				for ($i = 0, $max = imagecolorstotal($img); $i < $max; $i++)
				{
					$color = imagecolorsforindex($img, $i);
					imagecolorset($img, $i, $colors[floor(127 - $color['red'] / 2)][0], $colors[floor(127 - $color['red'] / 2)][1], $colors[floor(127 - $color['red'] / 2)][2]);
				}
			}
			else
			{
				/* Need some transparency, really? So we have to deal with each and every pixel */
				imagealphablending($img, false);
				imagesavealpha($img, true);
				for ($x = 0; $x < $this->width; $x++)
				{
					for ($y = 0; $y < $this->height; $y++)
					{
						/* Get blue value (0->255), divide it by 2, and apply transparency + colors */
						$blue = floor((imagecolorat($img, $x, $y) & 0xFF) / 2);
						imagesetpixel($img, $x, $y, floor($this->alpha * $blue / 127) * 16777216 + $colors[127 - $blue][0] * 65536 + $colors[127 - $blue][1] * 256 + $colors[127 - $blue][2]);
					}
				}
			}

			$grey  = imagecolorallocate($img, $this->__grey, $this->__grey, $this->__grey);
			$gray  = imagecolorallocate($img, ceil($this->__grey / 2), ceil($this->__grey / 2), ceil($this->__grey / 2));
			$white = imagecolorallocate($img, 255, 255, 255);
			$black = imagecolorallocate($img, 0, 0, 0);

			/* maxClicks */
			if ($image === 0 && $this->rainbow === true)
			{
				imagerectangle($img, 0, 0, 65, 11, $white);
				imagefilledrectangle($img, 0, 11, 65, 18, $white);
				imagestring($img, 1, 0, 11, '0', $black);
				$right = 66 - strlen($this->maxClicks) * 5;
				imagestring($img, 1, $right, 11, $this->maxClicks, $black);
				imagestring($img, 1, floor($right / 2) - 12, 11, 'clicks', $black);
			}

			if ($image === $nbOfImages - 1)
			{
				/* "No clicks under this line" message */
				if (defined('LANG_NO_CLICK_BELOW') === true)
				{
					imageline($img, 0, $this->height - 1, $this->width, $this->height - 1, $gray);
					imagestring($img, 1, 1, $this->height - 9, LANG_NO_CLICK_BELOW, $gray);
				}
				/* Copyleft */
				$this->drawCopyright($img,$grey,$gray);
			}

			/* Save PNG file */
			imagepng($img, sprintf($this->path.$this->file, $image));
			imagedestroy($img);
		}
		
		for ($i = 0; $i < 100; $i++)
		{
			imagedestroy($dots[$i]);
		}
		 
		return $files;
	}
	
	/**
	 * Colors creation:
	 * grey	=> deep blue (rgB)	=> light blue (rGB)	=> green (rGb)		=> yellow (RGb)		=> red (Rgb)
	 * 0	   $this->__colors[0]	   $this->__colors[1]	   $this->__colors[2]	   $this->__colors[3]	   128
	 * */
	private function createColors(){
		
		sort($this->__colors);
		$colors = array();
		for ($i = 0; $i < 128; $i++)
		{
		/* Red */
		if ($i < $this->__colors[0])
		{
		$colors[$i][0] = $this->__grey + ($this->__low - $this->__grey) * $i / $this->__colors[0];
		}
		elseif ($i < $this->__colors[2])
		{
		$colors[$i][0] = $this->__low;
		}
		elseif ($i < $this->__colors[3])
		{
		$colors[$i][0] = $this->__low + ($this->__high - $this->__low) * ($i - $this->__colors[2]) / ($this->__colors[3] - $this->__colors[2]);
		}
		else
		{
		$colors[$i][0] = $this->__high;
		}
		/* Green */
		if ($i < $this->__colors[0])
		{
		$colors[$i][1] = $this->__grey + ($this->__low - $this->__grey) * $i / $this->__colors[0];
		}
		elseif ($i < $this->__colors[1])
		{
		$colors[$i][1] = $this->__low + ($this->__high - $this->__low) * ($i - $this->__colors[0]) / ($this->__colors[1] - $this->__colors[0]);
		}
		elseif ($i < $this->__colors[3])
		{
		$colors[$i][1] = $this->__high;
		}
		else
		{
		$colors[$i][1] = $this->__high - ($this->__high - $this->__low) * ($i - $this->__colors[3]) / (127 - $this->__colors[3]);
		}
		/* Blue */
		if ($i < $this->__colors[0])
		{
		$colors[$i][2] = $this->__grey + ($this->__high - $this->__grey) * $i / $this->__colors[0];
		}
		elseif ($i < $this->__colors[1])
		{
		$colors[$i][2] = $this->__high;
		}
		elseif ($i < $this->__colors[2])
		{
		$colors[$i][2] = $this->__high - ($this->__high - $this->__low) * ($i - $this->__colors[1]) / ($this->__colors[2] - $this->__colors[1]);
		}
		else
		{
		$colors[$i][2] = $this->__low;
		}
		}
		
		return $colors;
	}//function createColors() end
	
	
	
	
	/**
	 * 根据情况向图像的右下角写入版权文字
	 * 
	 */
	private function drawCopyright($img,$grey,$gray){
		
		if ($this->copyleft === true)
		{
			imagestring($img, 1, $this->width - 160, $this->height - 9, 'Open source heatmap by ClickHeat', $grey);
			imagestring($img, 1, $this->width - 161, $this->height - 9, 'Open source heatmap by ClickHeat', $gray);
		}
	}
	
	

	/**
	 * Retourne une erreur / Returns an error
	 *
	 * @param string $error
	 * */
	function raiseError($error)
	{
		$this->error = $error;
		return false;
	}
	
	/**
	 * Do some tasks before drawing (database connection...)
	 **/
	function startDrawing()
	{
		$this->link = @mysql_connect($this->host, $this->user, $this->password);
		if ($this->link === false)
		{
			return $this->raiseError('Database connection error: '.mysql_error());
		}
		if (mysql_select_db($this->database) === false)
		{
			return $this->raiseError('Database selection error: '.$this->database);
		}
		
		//echo $this->maxQuery;
		$result = mysql_query($this->maxQuery);
		
		
		if ($result === false)
		{
			return $this->raiseError('Query failed: '.mysql_error());
		}
		$max = mysql_fetch_row($result);
		$this->maxY = $max[0];
		mysql_free_result($result);
		return true;
	}
	
	/**
	 * Find pixels coords and draw these on the current image
	 *
	 * @param integer $image Number of the image (to be used with $this->height)
	 * @return boolean Success
	 **/
	function drawPixels($image)
	{
		$limit = 0;
		do
		{
			/** Select with limit */
			$result = mysql_query(sprintf($this->query, $image * $this->height, ($image + 1) * $this->height - 1).' LIMIT '.$limit.','.$this->limit);
			if ($result === false)
			{
				return $this->raiseError('Query failed: '.mysql_error());
			}
			$count = mysql_num_rows($result);
	
			while ($click = mysql_fetch_row($result))
			{
				$x = (int) $click[0];
				$y = (int) ($click[1]  - $image * $this->height);
				if ($x < 0 || $x >= $this->width)
				{
					continue;
				}
				/** Apply a calculus for the step, with increases the speed of rendering : step = 3, then pixel is drawn at x = 2 (center of a 3x3 square) */
				$x -= $x % $this->step - $this->startStep;
				$y -= $y % $this->step - $this->startStep;
				/** Add 1 to the current color of this pixel (color which represents the sum of clicks on this pixel) */
				$color = imagecolorat($this->image, $x, $y) + 1;
				imagesetpixel($this->image, $x, $y, $color);
				$this->maxClicks = max($this->maxClicks, $color);
				if ($image === 0)
				{
					/** Looking for the maximum height of click */
					$this->maxY = max($y, $this->maxY);
				}
			}
			/** Free resultset */
			mysql_free_result($result);
	
			$limit += $this->limit;
		} while ($count === $this->limit);
		return true;
	}
	
	/**
	 * Do some cleaning or ending tasks (close database, reset array...)
	 **/
	function finishDrawing()
	{
		/** Close connection */
		mysql_close($this->link);
		return true;
	}

}

?>