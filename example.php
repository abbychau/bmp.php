<?php

include("bmp.php");

// Usage example:
$bmpData = decodeBMP("snail.bmp");
if ($bmpData) {
    print_r($bmpData['header']);
    echo "Pixel at (0, 0): " . implode(", ", $bmpData['pixels'][0][0]);
    // Access other header information and pixel data as needed
} else {
    echo "Invalid BMP file";
}


encodeBMP($bmpData['pixels'], "encoded.bmp");
echo "\nBMP file created successfully!";