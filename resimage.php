<?php

class ResImage {
	private $cachefile;
	// Directroy needs to be below the current working directory
	private $cacheDir = 'cache/';

	public function __construct($img, $w, $h) {
		date_default_timezone_set('GMT');

		// 404 if the file does not exist
		if(!$img || !file_exists($img)) return $this->headerNotFound();

		// Check if the cache directory exists or can be created
		if(!is_writable($this->cacheDir)) {
			if(is_dir($this->cacheDir)) {
				if(is_writable(getcwd())) {
					chmod($this->cacheDir, 0755);
				} else {
					$this->headerServerError("The directory $this->cacheDir is not writable. Please change the folder mode to 755");
				}
			} else if (is_writable(getcwd())){
				mkdir($this->cacheDir, 0755);
			} else {
				$this->headerServerError("Could not create $this->cacheDir directory. Please create $this->cacheDir directory with mode 755");
			}
		}

		$inode = fileinode($img);

		// Do a little prep to find the filename of the resized and scaled file, so we can test if it's cached
		$w ? $width = '-w' . $w : $width = '';
		$h ? $height = '-h' . $h : $height = '';

		$pi = pathinfo($img);

		// Define the cachefile name
		$this->cachefile = $this->cacheDir . $inode . $width . $height . '.' . $pi['extension'];

		$fileExists = file_exists($this->cachefile);

		if($fileExists) {
			$fileMimeType = $this->getMimeType($this->cachefile);
		}

		if($fileExists && $this->validateHeaders()) {
			// Browser cached file
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($this->cachefile)) . ' GMT', true, 304);
			exit;

		} else if ($fileExists && ($fileMimeType !== 'image/jpeg' && $fileMimeType !== 'image/png')) {
			// Only accept jpg and png files
			$this->headerNotFound();
		} else if (!$fileExists) {
			// Create the file
			$fileMimeType = $this->getMimeType($img);

			if($fileMimeType == 'image/jpeg') {
				$quality = 60;
				// Create image
				$i = $this->getNewJpeg($img);

				$i = $this->scaleImage($i, $img, $w, $h);

				// Create cache file
				imagejpeg($i, $this->cachefile, $quality);
				
			} else if ($fileMimeType == 'image/png') {
				$quality = 3;
				$i = $this->getNewPng($img);

				$i = $this->scaleImage($i, $img, $w, $h);

				imagepng($i, $this->cachefile, $quality);
			}

			// Tidy up
			imagedestroy($i);
		}

		// Return file with cache headers
		session_cache_limiter('public');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($this->cachefile)).' GMT', true, 200);
		header("Content-Type: $fileMimeType");
		readfile($this->cachefile);
	}

	private function scaleImage($i, $img, $w, $h) {
		// Get the dimensions of the original image
		$size = getimagesize($img);
		$origWidth = intval($size[0]);
		$origHeight = intval($size[1]);

		if(!$w || !$h) {
			if($w) {
				$h = $w * ($origHeight / $origWidth);
				$h = ~~$h; // Round down
			} else if($h) {
				$w = $h * ($origWidth / $origHeight);
				$w = ~~$w; //Round down
			} else {
				$w = $origWidth;
				$h = $origHeight;
			}
		}

		$origRatio = $origWidth / $origHeight;
		$ratio = $w / $h;

		// Calculate dimensions
		if($ratio == $origRatio) {
			$cropWidth = $origWidth;
			$cropHeight = $origHeight;
		} else if ($origRatio < $ratio) {
			$cropWidth = $origWidth;
			$cropHeight = ~~(($origRatio / $ratio) * $origHeight);
		} else if ($origRatio > $ratio) {
			$cropHeight = $origHeight;
			$cropWidth = ~~($ratio * $cropHeight);
		}

		$ci = imagecreatetruecolor($w, $h);

		imagecopyresampled($ci, $i, 0, 0, 0, 0, $w, $h, $cropWidth, $cropHeight);

		return $ci;
	}

	private function getNewJpeg($img) {
		if(!file_exists($img) || $this->getMimeType($img) !== 'image/jpeg') $this->headerNotFound();

		// Get a handle to the original image
		return imagecreatefromjpeg($img);  
	}

	private function getNewPng($img) {
		if(!file_exists($img) || $this->getMimeType($img) !== 'image/png') $this->headerNotFound();

		return imagecreatefrompng($img);
	}

	private function headerNotFound($msg = '') {
		header("HTTP/1.0 404 Not Found");
		echo $msg;
		exit;
	}

	private function headerServerError($msg = '') {
		header("HTTP/1.0 500 Internal Server Error", true, 500);
		echo $msg;
		exit;
	}

	private function validateHeaders() {
		// Getting headers sent by the client.
    $headers = apache_request_headers();

    return isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($this->cachefile));
	}

	private function getMimeType($filename) {
		$size = getimagesize($filename);

		return $size['mime'];
	}

	public function __destruct() {}
}
