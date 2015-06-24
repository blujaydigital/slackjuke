<?php
session_start();
include('lib/SpotifyJuke.php');

require('vendor/autoload.php');

use Lib\SpotifyJuke as SpotifyJuke;
use SpotifyWebAPI\Session as Session;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$provider = new Session(
    getenv('SPOTIFY_KEY'),
    getenv('SPOTIFY_SECRET'),
    getenv('SPOTIFY_REDIRECT')
);

$spotify = new SpotifyJuke($provider);

$action = isset($_GET['action']) ? trim($_GET['action'], '/') : '';

switch ($action) {
    case 'auth':
        $spotify->auth();
        break;
    case 'callback':
        echo 'Logged in - Now try the slack command';
        $spotify->callback();
        break;
    case 'add':
        $spotify->add();
        break;
    default:
        echo '<a href="/slackbot/spotifyjuke/auth">Log In</a>';
        break;
}