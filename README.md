# bmp.php

## Description

These are 2 simple PHP functions for creating and reading a BMP image file. It is based on the [BMP file format](https://en.wikipedia.org/wiki/BMP_file_format) and the [BMP file format specification](http://www.dragonwins.com/domains/getteched/bmp/bmpfileformat.htm).

## Usage

```php
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
echo "BMP file created successfully!";

```

## License

This project is licensed under the MIT License.

## The test image

The test image is a BMP image of a snail. 
Microsoft Corporation, Microsoft Windows Programmer's Reference, Volume 5; Messages, Structures, and Macros, Microsoft Press, 1993. (https://people.math.sc.edu/Burkardt/data/bmp/bmp.html)

![Snail](snail.bmp)