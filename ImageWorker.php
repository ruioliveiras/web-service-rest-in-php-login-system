<?php
/*
Version: 0.7
Date: 15/01/2014
Author: Rui Oliveira
*/

include('SimpleImage.php');
 
class ImageWorker {

	const sizeS = 64; 
	const sizeM = 128; 
	const sizel = 256;
	const sizeXL = 512; 
	
	public function __construct($imageTemp,$imageFinal){	
		$image = new SimpleImage();
   		$image->load($imageTemp);
		$func = "resizeToWidth";
		if ($image->getHeight()>$image->getWidth()){
			$func = "resizeToHeight";
		}
		$image->$func(self::sizeXL);
		$image->save($imageFinal.self::sizeXL.".jpeg");
		$image->$func(self::sizel);
		$image->save($imageFinal.self::sizel.".jpeg");
		$image->$func(self::sizeM);
		$image->save($imageFinal.self::sizeM.".jpeg");
		$image->$func(self::sizeS);
		$image->save($imageFinal.self::sizeS.".jpeg");
	}

}

?>
