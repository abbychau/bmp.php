<?php

function decodeBMP($filename) {
    // Open the file
    $file = fopen($filename, "rb");

    // Read the header data
    $header = unpack("vtype/Vsize/vreserved1/vreserved2/Voffset/VheaderSize/Vwidth/Vheight/vplanes/vbitsPerPixel/Vcompression/VimageSize/VxPixelsPerMeter/VyPixelsPerMeter/VcolorsUsed/VimportantColors", fread($file, 54));

    // Check if it's a valid BMP file
    if ($header['type'] != 0x4D42) {
        fclose($file);
        return false; // Not a BMP file
    }

    // Get the width and height
    $width = $header['width'];
    $height = $header['height'];

    // Calculate the number of bytes per pixel
    $bytesPerPixel = $header['bitsPerPixel'] / 8;

    // Calculate the row size (including padding)
    $rowSize = floor(($header['bitsPerPixel'] * $width + 31) / 32) * 4;

    // Read the pixel data
    $pixels = [];
    fseek($file, $header['offset']);
    for ($y = 0; $y < $height; $y++) {
        $row = [];
        for ($x = 0; $x < $width; $x++) {
            $pixelData = fread($file, $bytesPerPixel);
            $color = unpack("c" . $bytesPerPixel . "color", $pixelData);
            $row[] = $color;
        }
        fseek($file, $rowSize - $width * $bytesPerPixel, SEEK_CUR); // Skip padding
        $pixels[] = $row;
    }

    // Close the file
    fclose($file);

    // Return the decoded data
    return [
        'header' => $header,
        'pixels' => $pixels,
    ];
}

function encodeBMP($pixels, $filename) {
    // Get the image width and height
    $width = count($pixels[0]);
    $height = count($pixels);

    // Calculate the number of bytes per pixel
    $bytesPerPixel = 3; // Assuming 24-bit RGB format

    // Calculate the row size (including padding)
    $rowSize = floor((24 * $width + 31) / 32) * 4;

    // Calculate the total image size (including header and padding)
    $imageSize = $rowSize * $height;

    // Create the header data
    $header = pack("vVvvVVVVvvVVVVVV",
        0x4D42,                    // Signature ("BM")
        $imageSize + 54,           // Size of the BMP file in bytes
        0,                         // Reserved (0)
        0,                         // Reserved (0)
        54,                        // Offset to start of image data
        40,                        // Size of the BITMAPINFOHEADER structure
        $width,                    // Image width in pixels
        $height,                   // Image height in pixels
        1,                         // Number of planes (1)
        $bytesPerPixel * 8,        // Number of bits per pixel (24)
        0,                         // Compression type (0 = none)
        $imageSize,                // Size of image data in bytes (including padding)
        0,                         // Horizontal resolution in pixels per meter
        0,                         // Vertical resolution in pixels per meter
        0,                         // Number of colors in image (0 = all colors are important)
        0                          // Number of important colors (0 = all colors are important)
    );

    // Open the file for writing
    $file = fopen($filename, "wb");

    // Write the header data
    fwrite($file, $header);

    // Write the pixel data row by row (with padding)
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $color = $pixels[$y][$x];
            $pixelData = pack("CCC", $color['color1'], $color['color2'], $color['color3']);
            fwrite($file, $pixelData);
        }
        // Write row padding (if any)
        $paddingBytes = $rowSize - $width * $bytesPerPixel;
        for ($i = 0; $i < $paddingBytes; $i++) {
            fwrite($file, pack("C", 0));
        }
    }

    // Close the file
    fclose($file);
}
