<?php
session_start();
include ('lib/SpotifyJuke.php');

require('vendor/autoload.php');

use SpotifyWebAPI\Session   as Session;
use Lib\SpotifyJuke         as SpotifyJuke;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$provider = new Session(
                    getenv('SPOTIFY_KEY'),
                    getenv('SPOTIFY_SECRET'),
                    getenv('SPOTIFY_REDIRECT')
                );

$spotify = new SpotifyJuke($provider);


$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action){
    case 'auth':
        $spotify->auth();
        break;
    case 'callback':
        echo 'Logged In';
        $spotify->callback();
        break;
    case 'add':
        $spotify->add();
        break;
    default:
        echo '<a href="/auth">Log In</a>';
        break;
}