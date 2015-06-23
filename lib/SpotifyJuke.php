<?php
namespace Lib;

use SpotifyWebAPI\SpotifyWebAPI as SpotifyWebAPI;

class SpotifyJuke {

    private $_provider;
    private $_refreshToken;

    public function __construct (\SpotifyWebAPI\Session $provider){
        $this->_provider = $provider;
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
    }

    public function add(){

        $this->_provider->setRefreshToken($_SESSION['refresh_token']);
        $this->_provider->refreshAccessToken();

        $api = new SpotifyWebAPI;
       
        $api->setAccessToken($_SESSION['access_token']);
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
        $_SESSION['access_token']   = $this->_provider->getAccessToken();
        $_SESSION['refresh_token']  = $this->_provider->getRefreshToken();
    }
}