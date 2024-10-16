<?php

header('Access-Control-Allow-Origin:*');
header('Content-Type: multipart/form-data');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

require '../../../vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Send a 200 OK response for preflight requests
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_FILES['file']) || !isset($_POST['bucketName']) || !isset($_POST['fileName'])) {
        echo json_encode(['error' => 'File, bucketName, and fileName are required']);
        exit();
    }

    $file = $_FILES['file'];
    $bucketName = $_POST['bucketName'];
    $fileName = $_POST['fileName'];

    $mimeType = mime_content_type($file['tmp_name']);

    $storage = new StorageClient(['keyFilePath' =>  '../../agap-system-c677a8c0908d.json']);
    $bucket = $storage->bucket($bucketName);
    $object = $bucket->upload(
        fopen($file['tmp_name'], 'r'),
        [
            'name' => $fileName,
            'metadata' => [
                'contentType' => $mimeType
            ]
        ]
    );




    $url = $object->signedUrl(new \DateTime('tomorrow'));

    echo json_encode($url, JSON_UNESCAPED_SLASHES);
    exit();
}

echo json_encode(['error' => 'Invalid request method']);
exit();
