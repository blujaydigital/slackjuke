<?php
namespace Lib;

use SpotifyWebAPI\SpotifyWebAPI as SpotifyWebAPI;

class SpotifyJuke {

    private $_provider;
    private $_refreshToken;
    private $_db;

    public function __construct (\SpotifyWebAPI\Session $provider){
        $this->_provider = $provider;
        $this->_db = new \SQLite3("db/test.db");
    }

    public function auth (){
        // If we don't have an authorization code then get one
        $scopes = ['playlist-modify-public', 'playlist-modify-private'];

        $authUrl = $this->_provider->getAuthorizeUrl(array(
            'scope' => $scopes
        ));

        header('Location: '.$authUrl);
        die();
    }

    public function callback(){

        $this->_provider->requestAccessToken($_GET['code']);
        $this->_refresh();
        // See if an Sqlite DB exists
        $this->_createDB();
        $this->_getRefreshToken();
    }

    public function add(){

        $this->_provider->setRefreshToken($this->_getRefreshToken());
        $this->_provider->refreshAccessToken();

        $api = new SpotifyWebAPI;
       
        $api->setAccessToken($this->_getToken());
        $search = $_POST['search'];

        // Find out what we searched for
        $tracks     = $api->search($search, 'track');
        $track      = $tracks->tracks->items[0]; // Get the first track

        $track_id   = $track->id;
        $track_name = $track->name;

        echo 'Adding '. $track->artists[0]->name. ' - '. $track_name . "<br/>";

        // See if the track already exists 
        if($api->addUserPlaylistTracks(getenv('SPOTIFY_USERNAME'), getenv('SPOTIFY_PLAYLIST'), $track_id )){
                echo $track->artists[0]->name. ' - '. $track_name . " added!";
        }else {
                echo $track->artists[0]->name. ' - '. $track_name . " failed to add!";
        }
    }

    private function _refresh(){
        $this->_provider->refreshAccessToken();
        $result = $this->_db->query('UPDATE access SET token = "'.$this->_provider->getAccessToken().'"  WHERE key = "refresh"');
        $result = $this->_db->query('UPDATE access SET token = "'.$this->_provider->getRefreshToken().'" WHERE key = "refresh"');
    }

    private function _getToken(){
        $result = $this->_db->query('SELECT key, token FROM access WHERE key = "access"');
        $result = $result->fetchArray();
        return $result['token'];
    }

    private function _getRefreshToken(){
        $result = $this->_db->query('SELECT key, token FROM access WHERE key = "refresh"');
        $result = $result->fetchArray();
        return $result['token'];
    }

    private function _createDB(){
        $result = $this->_db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='access'");
        if (!$result){ // Table doesn't exist, create it!
            $this->_db->exec('CREATE TABLE access (key STRING, token STRING)');
            $this->_db->exec("INSERT INTO access (key, token) VALUES ('access', '".$this->_provider->getAccessToken()."')");
            $this->_db->exec("INSERT INTO access (key, token) VALUES ('refresh', '".$this->_provider->getRefreshToken()."')");
        }
    }
}