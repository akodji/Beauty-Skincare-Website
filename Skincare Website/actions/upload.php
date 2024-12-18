<!DOCTYPE html>
<html>
<head>
    <title>File Upload Test</title>
</head>
<body>
    <h2>File Upload Test</h2>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_FILES["uploadedFile"])) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $target_file = $target_dir . basename($_FILES["uploadedFile"]["name"]);
            $uploadOk = 1;
            
            // Check if file already exists
            if (file_exists($target_file)) {
                echo "Sorry, file already exists.<br>";
                $uploadOk = 0;
            }
            
            // Check file size (64MB limit)
            if ($_FILES["uploadedFile"]["size"] > 64000000) {
                echo "Sorry, your file is too large.<br>";
                $uploadOk = 0;
            }
            
            if ($uploadOk == 0) {
                echo "Sorry, your file was not uploaded.<br>";
            } else {
                if (move_uploaded_file($_FILES["uploadedFile"]["tmp_name"], $target_file)) {
                    echo "The file ". htmlspecialchars(basename($_FILES["uploadedFile"]["name"])). " has been uploaded.<br>";
                } else {
                    echo "Sorry, there was an error uploading your file.<br>";
                }
            }
        }
    }
    ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="uploadedFile" id="uploadedFile">
        <input type="submit" value="Upload File" name="submit">
    </form>
    
    <h3>Current Uploads:</h3>
    <?php
    $target_dir = "uploads/";
    if (is_dir($target_dir)) {
        $files = scandir($target_dir);
        foreach($files as $file) {
            if ($file != "." && $file != "..") {
                echo htmlspecialchars($file) . "<br>";
            }
        }
    }
    ?>
</body>
</html>
