<?php
namespace Lib;

use SpotifyWebAPI\Session as Session;
use SpotifyWebAPI\SpotifyWebAPI as SpotifyWebAPI;

class SpotifyJuke
{

    private $_provider;
    private $_refreshToken;
    private $_db;

    /**
     * @param Session $provider
     */
    public function __construct(Session $provider)
    {
        $this->_provider = $provider;
        $this->_db = new \SQLite3("db/test.db");
    }

    /**
     * Auth function, redirect the user to
     * the oauth login page
     */
    public function auth()
    {
        $scopes = ['playlist-modify-public', 'playlist-modify-private'];

        $authUrl = $this->_provider->getAuthorizeUrl(array(
            'scope' => $scopes
        ));

        header('Location: ' . $authUrl);
        die();
    }

    /**
     * Handle the response from Spotify
     * and store the Access tokens.
     */
    public function callback()
    {
        $this->_provider->requestAccessToken($_GET['code']);
        $this->_refresh();
        // See if an Sqlite DB exists
        $this->_createDB();
        $this->_getRefreshToken();
    }

    /**
     * Update the Access/ refresh tokens
     */
    private function _refresh()
    {
        $this->_provider->refreshAccessToken();
        $this->_db->query('UPDATE access SET token = "' . $this->_provider->getAccessToken() . '"  WHERE key = "access"');
        $this->_db->query('UPDATE access SET token = "' . $this->_provider->getRefreshToken() . '" WHERE key = "refresh"');
    }

    /**
     * Create the SQLite database if it
     * doesn't already exist.
     */
    private function _createDB()
    {
        $result = $this->_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='access'");
        if (!$result) { // Table doesn't exist, create it!
            $this->_db->exec('CREATE TABLE access (key STRING, token STRING)');
            $this->_db->exec("INSERT INTO access (key, token) VALUES ('access', '" . $this->_provider->getAccessToken() . "')");
            $this->_db->exec("INSERT INTO access (key, token) VALUES ('refresh', '" . $this->_provider->getRefreshToken() . "')");
        }
    }

    /**
     * Returns the refresh token
     */
    private function _getRefreshToken()
    {
        $result = $this->_db->query('SELECT key, token FROM access WHERE key = "refresh"');
        $result = $result->fetchArray();

        return $result['token'];
    }

    /**
     * Handle the Slack Command
     */
    public function add()
    {
        $this->_provider->setRefreshToken($this->_getRefreshToken());
        $this->_provider->refreshAccessToken();

        $api = new SpotifyWebAPI;

        $api->setAccessToken($this->_getToken());
        $search = $_POST['text'];

        if (strlen($search) < 2) {
            echo 'Please enter in a more specific search phrase';
        }

        // Find out what we searched for
        $tracks = $api->search($search, 'track');
        $track = $tracks->tracks->items[0]; // Get the first track

        if (count($tracks->tracks->items) == 0){
            echo 'Error: Song not found!';
            die();
        }

        $track_id = $track->id;
        $track_name = $track->name;

        // See if the track already exists
        if ($api->addUserPlaylistTracks(getenv('SPOTIFY_USERNAME'), getenv('SPOTIFY_PLAYLIST'), $track_id)) {
            echo $track->artists[0]->name . ' - ' . $track_name . " added!";
        } else {
            echo $track->artists[0]->name . ' - ' . $track_name . " failed to add!";
        }
    }

    /**
     * Get the Access token from the DB
     */
    private function _getToken()
    {
        $result = $this->_db->query('SELECT key, token FROM access WHERE key = "access"');
        $result = $result->fetchArray();

        return $result['token'];
    }
}