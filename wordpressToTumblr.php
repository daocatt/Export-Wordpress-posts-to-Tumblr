<?php

// Set the following parameters
$tumblrEmail = '';
$tumblrPassword = '';
$tumblrHostname = '';                       // support group blog, e.g. myblog.tumblr.com or yourblog.tumblr.com
$file = 'test.xml';                         // location of exported wordpress xml
$categories = array();                      // e.g. blog, news, videos
$oldDomain = '';                            // e.g. mywordpress.com (set this if you want relative links in posts converted to absolute before importing… note this should only be done if your old site will be set up to forward links to your new one.)


$resolver = array();
$tumblr = new Tumblr();
$tumblr->setCredentials($tumblrEmail, $tumblrPassword, $tumblrHostname);

$wpt = new WordpressToTumblr();

$wpt->setWordpressXmlFile($file);
$wpt->loadXML();

$wpt->setTumblrObject($tumblr);

$wpt->postToTumblr();

class WordpressToTumblr {

    private $wpExportFile;
    private $xml;
    private $tumblr;

    public function setWordpressXmlFile($file) {
        $this->wpExportFile = $file;
    }

    public function loadXML() {
        $this->xml = simplexml_load_file($this->wpExportFile);
    }

    public function setTumblrObject($obj) {
        $this->tumblr = $obj;
    }

    public function postToTumblr() {

		global $resolver, $categories;

        $ns = $this->xml->getNamespaces(true);
        foreach ($this->xml->channel[0]->item as $item) 
        {
            // skip drafts and other unpublished content
            if ((string)$item->children($ns['wp'])->status != 'publish')
            	continue;
            // only take posts from specific categories
            if (!empty($categories))
            {
                $title = $item->xpath('category');
                $title[1]['nicename'] = urldecode($title[1]['nicename']);   //urldecode suppport more language
	            if (!in_array((string)$title[1]['nicename'], $categories))
	            	continue;
			}
			        	
        	// get the title and body
            $title = (string)$item->title;
            $body = (string)$item->children($ns['content'])->encoded;
            
            // get the extra parameters
            $params = array(
            	'date' 		=>	(string)$item->children($ns['wp'])->post_date,
            	'slug'		=> 	(string)$item->children($ns['wp'])->post_name,
            );
            
            // get the tags
            $tagNodes = $tags = array();
            $tagNodes = $item->xpath('category');

            foreach($tagNodes as $child){
				if ($child['domain'] == 'post_tag' && $child['nicename'])
					$tags[(string)$child['nicename']] = urldecode($child['nicename']);
			}
            if ($tags)
            {
                $params['tags'] = join(',', $tags);
            }
			// change relative urls to absolute (pointing to old wordpress site)
			if (!empty($oldDomain))
				$body = str_replace('href="/', 'href="http://'.$oldDomain.'/', $body);
            
            // post it
            $postId = $this->tumblr->postRegular( $title , $body, $params);
            
            // get link
            $node = $item->xpath('link');
            $resolver[(string)$node[0]] = '/post/'.$postId.'/'.$params['slug'];
        }
    
    	foreach($resolver as $key => $item)
		{
			echo "'$key' => '$item'<br />";
		}
    }
}

class Tumblr {

    private $pass;
    private $email;
    private $hostname;
    private $baseUrl = 'http://www.tumblr.com/api/';
    private $postParameters = array(
        'type' => null,
        'generator' => null,
        'date' => null,
        'private' => '0',
        'tags' => null,
        'format' => 'html', //support html,markdown
        'slug' => null,
        'state' => 'published',
        'send-to-twitter' => 'no'
    );

    public function setCredentials($user, $pass, $hostname='') {
        $this->email = $user;
        $this->pass = $pass;
        $this->hostname = $hostname;
    }

    private function write($postType, $params = array()) {
        if (empty($params)) {
            return false;
        }
        // add tumblr credentials
        $params = array_merge(
                $params, array('email' => $this->email, 'password' => $this->pass, 'group' => $this->hostname)
        );
        $tumblrURL = $this->baseUrl . 'write';
        return $this->executeRequest($tumblrURL, $params);
    }

    public function postRegular($title, $body, $additionalParams = array()) {
        if (empty($title) || empty($body)) {
            return false;
        }

        //$params = array_intersect_key($additionalParams, $this->postParameters);
        $params = $additionalParams+$this->postParameters;
        $params = array_merge(
                $params, array('title' => $title, 'body' => $body, 'type' => 'regular')
        );

        return $this->write('regular',$params);
    }

    private function executeRequest($url, $params) {
        
        $data = http_build_query($params);
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($c);
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        // Check for success
        if ($status == 201) {
            echo "Success! The request executed succesfully.\n";
            return $result;
        } else if ($status == 403) {
            echo 'Bad email or password';
            return false;
        } else {
            echo "Error: $result\n";
            return false;
        }
    }

}

?>
