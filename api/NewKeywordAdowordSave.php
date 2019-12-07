<?php
/*if (!isset($_SESSION)) {
    session_start();
}*/
include __DIR__ . '/vendor/autoload.php';

require_once 'functions.php';

// init:
$depth = '/../../../';
define( 'SRC_PATH', dirname( __FILE__ ) . $depth . 'src/' );
define( 'LIB_PATH', 'Google/Api/Ads/AdWords/Lib' );
define( 'UTIL_PATH', 'Google/Api/Ads/Common/Util' );
define( 'ADWORDS_UTIL_PATH', 'Google/Api/Ads/AdWords/Util' );

define( 'ADWORDS_VERSION', 'v201409' );

// Configure include path
ini_set( 'include_path', implode( array(
    ini_get( 'include_path' ),
    PATH_SEPARATOR,
    SRC_PATH
) ) );

# config:
$config = require realpath( __DIR__ . '/../' ) . '/config.php';

try {
    // Get AdWordsUser from credentials in "../auth.ini"
    // relative to the AdWordsUser.php file's directory.

    $keywordCount = '';//$_POST['keywordCount'];
    $projectId    = $argv[1];

    $user = new AdWordsUser();

    // Log every SOAP XML request and response.
    //$user->LogAll();

    // Run the example.
    EstimateKeywordTrafficExample( $user, $projectId, $keywordCount, $config );
} catch ( Exception $e ) {
    printf( "An error has occurred: %s\n", $e->getMessage() );
    echo "<br/>";
}
?>