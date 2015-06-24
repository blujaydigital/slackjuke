<?php
namespace Lib;

use SpotifyWebAPI\Session as Session;
use SpotifyWebAPI\SpotifyWebAPI as SpotifyWebAPI;

class SpotifyJuke
{

    private $_provider;
    private $_refreshToken;
    private $_db;
    private $_api;

    /**
     * @param Session $provider
     */
    public function __construct(Session $provider)
    {
        $this->_provider    = $provider;
        $this->_db          = new \SQLite3("db/test.db");
        $this->_api         = new SpotifyWebAPI;
    }

    /**
     * Auth function, redirect the user to
     * the oauth login page
     */
    public function auth()
    {
        $scopes = ['playlist-modify-public', 'playlist-modify-private'];

        $authUrl = $this->_provider->getAuthorizeUrl(array(
            'scope' => $scopes,
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

        if ($this->_provider->refreshAccessToken()) {
            $this->_refresh();
            $this->_api->setAccessToken($this->_getToken());

            $search = $_POST['text'];

            // Check for system command
            $this->_processCommands($search);
           
            if (strlen($search) < 2) {
                echo 'Please enter in a more specific search phrase';
            }

            
            try {
                // Perform the search
                $tracks = $this->_api->search($search, 'track');
                $track = $tracks->tracks->items[0]; // Get the first track
            } catch(Exception $e){
                echo 'Caught Exception:'. $e->getMesage();
                echo 'Attempting to Reauth - Please try again!';
                $this->auth();
            }
            
            if (count($tracks->tracks->items) == 0) {
                echo 'Error: Song not found!';
                die();
            }
            
            // See if the track already exists
            if ($this->_api->addUserPlaylistTracks(getenv('SPOTIFY_USERNAME'), getenv('SPOTIFY_PLAYLIST'), $track->id)) {
                echo $track->artists[0]->name . ' - ' . $track->name . " added! - ".$this->_getToken();
            } else {
                echo $track->artists[0]->name . ' - ' . $track->name . " failed to add!";
            }
        } else {
            echo 'Unable to refresh access token - please try authenticate again.';
        }
    }

    private function _processCommands($command){

        // TODO - Check user auth

        // Check to see if it's a system command
        if (strpos($command, '!!') === 0){
            $command = substr($command,2);
            // It is! Check to see which command.
            switch ($command){  
                case 'wipe':
                    $this->_wipe();
                    break;
                default:
                    echo 'Invalid system command';
                    break;
            }
            die();
        } else {
            // Not a system command, continue with normal flow;
            return false;
        }
    }

    /**
     * Remove the contents of the play list
     * @return null;
     */
    private function _wipe(){
            echo 'Wiping Playlist.';
            // Set the playlist to an empty array
            $this->_api->replacePlaylistTracks(getenv('SPOTIFY_USERNAME'), getenv('SPOTIFY_PLAYLIST'), array());
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