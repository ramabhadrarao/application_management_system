<?php
if (extension_loaded('pdo') && in_array('mysql', PDO::getAvailableDrivers())) {
    echo "✅ PDO MySQL is working!";
} else {
    echo "❌ PDO MySQL still not available";
}
?>