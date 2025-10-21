<?php
require "config/config.php";
require_once 'vendor/autoload.php';

// --- Basic Setup ---
$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

// Define the base path of your project.
define('BASE_PATH', '/LostAndFoundManagementSystem');

// *** Make BASE_PATH available in all Twig templates ***
$twig->addGlobal('BASE_PATH', BASE_PATH);

// --- ROUTING LOGIC ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = str_replace(BASE_PATH, '', $request_uri);

if (empty($route)) {
    $route = '/';
}

// This makes the '$route' variable available as 'current_route' in all Twig files.
$twig->addGlobal('current_route', $route);

switch ($route) {
    case '/':
    case '/home':
        $name = "paul";
        $items = $conn->query("SELECT * FROM item");
        $items->execute();
        $allItems = $items->fetchAll(PDO::FETCH_OBJ);

        echo $twig->render('forum.html.twig', [
            'items' => $allItems,
            'name' => $name
        ]);
        break; 

    case '/link':
        echo $twig->render('link.html.twig');
        break; 

    default:
        http_response_code(404);
        // It's better to render a real 404 page
        echo $twig->render('404.html.twig');
        break;
}
?>