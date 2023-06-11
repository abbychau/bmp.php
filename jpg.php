<?php

function createMarkers($pixels) {
    // Get the image width and height
    $width = count($pixels[0]);
    $height = count($pixels);

    // Create the SOI marker
    $soi = pack("n", 0xFFD8); // Start of Image marker

    // Create the APP0 marker (JFIF header)
    $jfifHeader = pack("nna5nnnCC",
        0xFFE0,          // APP0 marker
        0x0010,          // Length (16 bytes)
        0x4A46494600,    // Identifier "JFIF\0"
        0x0102,          // Version (1.02)
        0x0000,          // Units (0 = none)
        0x0001,          // X density (1 pixel per unit)
        0x0001           // Y density (1 pixel per unit)
    );

    // Create the DQT marker (Quantization Table)
    $dqtMarker = pack("nna65",
        0xFFDB,          // DQT marker
        0x0043,          // Length (67 bytes)
        "\x00\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01" .
        "\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01" .
        "\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01" .
        "\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01\x01" .
        "\x01"
    );

    // Combine the markers
    $markers = $soi . $jfifHeader . $dqtMarker;

    return $markers;
}


function rgbToYCbCr($pixels) {
    $transformedPixels = [];

    foreach ($pixels as $row) {
        $transformedRow = [];
        foreach ($row as $pixel) {
            [$r, $g, $b] = $pixel;

            // Convert RGB to YCbCr
            $y = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            $cb = 128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
            $cr = 128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;

            // Store the transformed pixel
            $transformedRow[] = [$y, $cb, $cr];
        }
        $transformedPixels[] = $transformedRow;
    }

    return $transformedPixels;
}

function blockSplitting($pixels) {
    $blocks = [];

    $height = count($pixels);
    $width = count($pixels[0]);

    for ($y = 0; $y < $height; $y += 8) {
        for ($x = 0; $x < $width; $x += 8) {
            $block = [];
            for ($i = 0; $i < 8; $i++) {
                $row = [];
                for ($j = 0; $j < 8; $j++) {
                    $row[] = $pixels[$y + $i][$x + $j];
                }
                $block[] = $row;
            }
            $blocks[] = $block;
        }
    }

    return $blocks;
}

function applyDCT($blocks) {
    $dctBlocks = [];

    foreach ($blocks as $block) {
        $dctBlock = [];
        for ($u = 0; $u < 8; $u++) {
            $row = [];
            for ($v = 0; $v < 8; $v++) {
                $sum = 0;
                for ($x = 0; $x < 8; $x++) {
                    for ($y = 0; $y < 8; $y++) {
                        $cosineU = cos((2 * $x + 1) * $u * pi() / 16);
                        $cosineV = cos((2 * $y + 1) * $v * pi() / 16);
                        $sum += $block[$x][$y] * $cosineU * $cosineV;
                    }
                }
                $alphaU = ($u === 0) ? 1 / sqrt(2) : 1;
                $alphaV = ($v === 0) ? 1 / sqrt(2) : 1;
                $dctValue = 0.25 * $alphaU * $alphaV * $sum;
                $row[] = $dctValue;
            }
            $dctBlock[] = $row;
        }
        $dctBlocks[] = $dctBlock;
    }

    return $dctBlocks;
}


function applyQuantization($dctBlocks, $quantizationTable) {
    $quantizedBlocks = [];

    foreach ($dctBlocks as $block) {
        $quantizedBlock = [];
        for ($i = 0; $i < 8; $i++) {
            $row = [];
            for ($j = 0; $j < 8; $j++) {
                $quantizedValue = round($block[$i][$j] / $quantizationTable[$i][$j]);
                $row[] = $quantizedValue;
            }
            $quantizedBlock[] = $row;
        }
        $quantizedBlocks[] = $quantizedBlock;
    }

    return $quantizedBlocks;
}


function generateHuffmanCodeMap($quantizedBlocks) {
    $frequencyMap = [];
    $huffmanCodeMap = [];

    // Step 1: Calculate the frequency of each quantized coefficient
    foreach ($quantizedBlocks as $block) {
        foreach ($block as $row) {
            foreach ($row as $coefficient) {
                if (!isset($frequencyMap[$coefficient])) {
                    $frequencyMap[$coefficient] = 0;
                }
                $frequencyMap[$coefficient]++;
            }
        }
    }

    // Step 2: Construct the Huffman tree
    $huffmanTree = new SplPriorityQueue();
    foreach ($frequencyMap as $coefficient => $frequency) {
        $huffmanTree->insert([$coefficient], -$frequency);
    }

    while ($huffmanTree->count() > 1) {
        $firstNode = $huffmanTree->extract();
        $secondNode = $huffmanTree->extract();

        $mergedNode = array_merge($firstNode, $secondNode);
        $huffmanTree->insert($mergedNode, array_sum($firstNode) + array_sum($secondNode));
    }

    $huffmanRoot = $huffmanTree->top();

    // Step 3: Traverse the Huffman tree to generate Huffman codes
    generateHuffmanCodes($huffmanRoot, '', $huffmanCodeMap);

    return $huffmanCodeMap;
}

function generateHuffmanCodes($node, $code, &$huffmanCodeMap) {
    if (is_array($node)) {
        generateHuffmanCodes($node[0], $code . '0', $huffmanCodeMap);
        generateHuffmanCodes($node[1], $code . '1', $huffmanCodeMap);
    } else {
        $huffmanCodeMap[$node] = $code;
    }
}

function entropyEncode($quantizedBlocks, $huffmanCodeMap) {
    $encodedData = '';

    foreach ($quantizedBlocks as $block) {
        foreach ($block as $row) {
            foreach ($row as $coefficient) {
                $encodedData .= $huffmanCodeMap[$coefficient];
            }
        }
    }

    return $encodedData;
}




function writeJPEG($pixels, $filename) {

    $quantizationTable = [
        [16, 11, 10, 16, 24, 40, 51, 61],
        [12, 12, 14, 19, 26, 58, 60, 55],
        // More quantization values here...
    ];

    $blocks = blockSplitting($pixels);
    $dctBlocks = applyDCT($blocks);
    $quantizedBlocks = applyQuantization($dctBlocks, $quantizationTable);
    $huffmanCodeMap = generateHuffmanCodeMap($quantizedBlocks);
    $encodedData = entropyEncode($quantizedBlocks, $huffmanCodeMap);

    $jfifHeader = pack("nCCCCCCCCCCCCn",
        0xFFD8, 0xFFE0, 0x00, 0x10, ord('J'), ord('F'), ord('I'), ord('F'), 0x00, 0x01, 0x02, 0x03, 0x00, 0x3F
    );

    $huffmanMarkers = createMarkers($huffmanCodeMap);

    $fileContents = $jfifHeader . $huffmanMarkers . $encodedData;

    file_put_contents($filename, $fileContents);
}