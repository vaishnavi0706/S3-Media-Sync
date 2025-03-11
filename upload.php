<?php
require 'vendor/autoload.php'; // Autoload AWS SDK

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// AWS S3 Configuration
$s3Bucket = 'mybuc.2002'; // bucket_name
$region = 'ap-south-1'; // e.g., us-west-2
$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => $region,
    //'credentials' => [
    //    'key'    => 'your-access-key-id',
    //    'secret' => 'your-secret-access-key',
    //],
]);

// RDS (MySQL) Database Configuration
$servername = "database-1.cp0wuao8ky1o.ap-south-1.rds.amazonaws.com";
$username = "admin";
$password = "vaish0706";
$dbname = "facebook";

// Connect to RDS
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Collect form data
    $name = htmlspecialchars($_POST['name']);
    $surname = htmlspecialchars($_POST['surname']);
    $gender = htmlspecialchars($_POST['gender']);
    $email = htmlspecialchars($_POST['email']);

    // File upload settings
    $file = $_FILES["anyfile"];
    $target_file = basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if the file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if($check !== false) {
        $uploadOk = 1;
    } else {
        echo "File is not an image.<br>";
        $uploadOk = 0;
    }

    // Check file size (limit set to 2MB)
    if ($file["size"] > 2000000) {
        echo "Sorry, your file is too large.<br>";
        $uploadOk = 0;
    }

    // Allow certain file formats (JPG, PNG, JPEG, GIF)
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif" ) {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.<br>";
        $uploadOk = 0;
    }
  // If file upload conditions are met
    if ($uploadOk == 1) {
        // Upload file to S3
        try {
            $result = $s3Client->putObject([
                'Bucket' => $s3Bucket,
                'Key'    => $target_file,
                'SourceFile' => $file["tmp_name"],
                'ACL'    => 'public-read', // Make the file publicly accessible
            ]);

            // Get the URL of the uploaded file
            $s3FileUrl = $result['ObjectURL'];

            // Insert form data into RDS database
            $stmt = $conn->prepare("INSERT INTO users (name, surname, gender, email, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $surname, $gender, $email, $s3FileUrl);

            if ($stmt->execute()) {
                echo "Record inserted successfully. <br>";
                echo "File uploaded to S3: <a href='" . $s3FileUrl . "'>View Image</a><br>";
            } else {
                echo "Error: " . $stmt->error;
            }

            $stmt->close();

        } catch (AwsException $e) {
            echo "Error uploading file to S3: " . $e->getMessage();
        }
    }

    $conn->close();
}
?>
