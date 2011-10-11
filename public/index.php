<?php
require_once "../include/config.php";

// connect to database
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);

// prepared SQL statements
$sqlSelectImages = "SELECT * FROM images WHERE post_id = :post_id";
$stmSelectImages = $db->prepare($sqlSelectImages);

// output each blog post
$sqlSelectPosts = "SELECT * FROM blog_posts ORDER BY create_ts DESC";
$result = $db->query($sqlSelectPosts);
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $stmSelectImages->execute(array(":post_id" => $row["post_id"]));
    $images = $stmSelectImages->fetchAll();

    echo "<div>";
    echo "<h2>" . htmlspecialchars($row["title"]) . "</h2>";
    echo "<p>" . htmlspecialchars($row["body"]) . "</p>";
    if (!empty($images)) {
        // output thumbnail and link for each image
        foreach ($images as $img) {
            $ext = "." . pathinfo($img["image_path"], PATHINFO_EXTENSION);
            $thmb = str_replace($ext, "_t" . $ext, $img["image_path"]);
            echo '<a href="' . $img["image_path"] . '">';
            echo '<img src="' . $thmb . '" alt="' . basename($img["image_path"]) . '"/>';
            echo "</a>";
        }
    }
    echo "</div>";
}
