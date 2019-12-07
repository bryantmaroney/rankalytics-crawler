<?php
function EstimateKeywordTrafficExample( AdWordsUser $user, $projectId, $keywordCount, $config )
{
    //default pre-sets:
    $keywords = $result_array = $update_array_array = array();

    // Get the service, which loads the required classes.
    $trafficEstimatorService = $user->GetService( 'TrafficEstimatorService', ADWORDS_VERSION );

    // Create keywords. Up to 2000 keywords can be passed in a single request.
    /*mysql_query("set max_length_for_sort_data = 2048");*/ // ?

    $dbo           = new DbHandle( $config );
    $temp_keywords = $dbo->getResults( "SELECT * FROM \"tbl_project_keywords\" WHERE length(keyword)< 80 AND project_id='" . $projectId . "' ORDER BY \"uploadedOn\" desc" );
    unset( $dbo );

    if (count( $temp_keywords ) == 0) {
        exit( 'empty result. What to do?' );
    }

    foreach ($temp_keywords as $t_no => $row) {
        $keywords[]     = new Keyword( urlencode( $row['keyword'] ), 'EXACT' );
        $keywords_arr[] = $row;
    }

    // Negative keywords don't return estimates, but adjust the estimates of the
    // other keywords in the hypothetical ad group.
    $negativeKeywords   = array();
    $negativeKeywords[] = new Keyword( 'moon walk', 'BROAD' );

    // Create a keyword estimate request for each keyword.
    $keywordEstimateRequests = array();
    foreach ($keywords as $keyword) {
        $keywordEstimateRequest          = new KeywordEstimateRequest();
        $keywordEstimateRequest->keyword = $keyword;

        $keywordEstimateRequests[] = $keywordEstimateRequest;
    }

    // Create a keyword estimate request for each negative keyword.
    foreach ($negativeKeywords as $negativeKeyword) {
        $keywordEstimateRequest          = new KeywordEstimateRequest();
        $keywordEstimateRequest->keyword = $negativeKeyword;

        $keywordEstimateRequest->isNegative = true;
        $keywordEstimateRequests[]          = $keywordEstimateRequest;
    }


    // Create ad group estimate requests.
    $adGroupEstimateRequest                          = new AdGroupEstimateRequest();
    $adGroupEstimateRequest->keywordEstimateRequests = $keywordEstimateRequests;
    $adGroupEstimateRequest->maxCpc                  = new Money( 1000000 );


    // Create campaign estimate requests.
    $campaignEstimateRequest                            = new CampaignEstimateRequest();
    $campaignEstimateRequest->adGroupEstimateRequests[] = $adGroupEstimateRequest;

    // Set targeting criteria. Only locations and languages are supported.
    $germany                             = new Location();
    $germany->id                         = 2276;
    $campaignEstimateRequest->criteria[] = $germany;

    $lang_de                             = new Language();
    $lang_de->id                         = 1001;
    $campaignEstimateRequest->criteria[] = $lang_de;

    // Create selector.
    $selector                             = new TrafficEstimatorSelector();
    $selector->campaignEstimateRequests[] = $campaignEstimateRequest;

    // Make the get request.
    $result = $trafficEstimatorService->get( $selector );

    // Display results.

    $keywordEstimates = $result->campaignEstimates[0]->adGroupEstimates[0]->keywordEstimates;

    //die();
    for ($i = 0; $i < sizeof( $keywordEstimates ); $i ++) {
        $keywordEstimateRequest = $keywordEstimateRequests[$i];
        // Skip negative keywords, since they don't return estimates.
        if ( ! $keywordEstimateRequest->isNegative) {
            $keyword         = $keywordEstimateRequest->keyword;
            $keywordEstimate = $keywordEstimates[$i];
            /*echo '<pre>';
            print_r($keywordEstimate);
            echo '</pre>';*/

            // Find the mean of the min and max values.
            $meanAverageCpc      = ( $keywordEstimate->min->averageCpc->microAmount + $keywordEstimate->max->averageCpc->microAmount ) / 2;
            $meanAveragePosition = ( $keywordEstimate->min->averagePosition + $keywordEstimate->max->averagePosition ) / 2;
            $meanClicks          = ( $keywordEstimate->min->clicksPerDay + $keywordEstimate->max->clicksPerDay ) / 2;
            $meanTotalCost       = ( $keywordEstimate->min->totalCost->microAmount + $keywordEstimate->max->totalCost->microAmount ) / 2;

            //printf("Results for the keyword with text '%s' and match type '%s':\n", $keyword->text, $keyword->matchType);
            /*echo "<hr/>";
            printf("  Estimated average CPC in micros: %.0f\n", $meanAverageCpc);
            echo "<hr/>";*/
            /*printf("  Estimated ad position: %.2f \n", $meanAveragePosition);
            echo "<br/>";
            printf("  Estimated daily clicks: %d\n", $meanClicks);
            echo "<br/>";
            printf("  Estimated daily cost in micros: %.0f\n\n", $meanTotalCost);
            echo "<br/>";*/

            $keyword_info[$i]               = GetKeywordIdeasExample( $user, $keyword->text );
            $keyword_info[$i]['keyword']    = $keywords_arr[$i]['keyword'];
            $keyword_info[$i]['keyword_id'] = $keywords_arr[$i]['unique_id'];
            $keyword_info[$i]['cpc']        = $meanAverageCpc;

            //print_r($keyword_info[$i]);
            insert_into_table( $keyword_info[$i], $config );
        }
    }
}

function insert_into_table( $data_array, $config )
{
    $insert_query = "INSERT INTO project_keywords_adwordinfo (keyword_id,keyword,\"CPC\",volume,competition,created_on) VALUES
    ('" . $data_array['keyword_id'] . "','" . $data_array['keyword'] . "','" . $data_array['cpc'] . "','" . $data_array['volume'] . "','" . $data_array['competition'] . "', now() )";

    $dbo = new DbHandle( $config );
    $r   = $dbo->runQuery( $insert_query );
    unset( $dbo );

    if ($r) {
        echo '<br>inserted record for keyword ' . $data_array['keyword'];
    } else {
        echo '<br>Failed to insert record for keyword ' . $data_array['keyword'];
    }
}

function GetKeywordIdeasExample( AdWordsUser $user, $kw )
{
    // Get the service, which loads the required classes.
    $targetingIdeaService = $user->GetService( 'TargetingIdeaService', ADWORDS_VERSION );

    // Create seed keyword.
    $keyword = $kw;
    // Create selector.
    $selector              = new TargetingIdeaSelector();
    $selector->requestType = 'STATS';
    //$selector->requestType = 'IDEAS';
    $selector->ideaType = 'KEYWORD';

    /*$selector->requestedAttributeTypes = array('KEYWORD_TEXT', 'SEARCH_VOLUME', 'CATEGORY_PRODUCTS_AND_SERVICES', 'TARGETED_MONTHLY_SEARCHES', 'COMPETITION');
    $selector->requestedAttributeTypes = array('KEYWORD_TEXT', 'SEARCH_VOLUME', 'COMPETITION');*/

    $selector->requestedAttributeTypes = array( 'KEYWORD_TEXT', 'SEARCH_VOLUME', 'COMPETITION', 'AVERAGE_CPC' );

    // Create language search parameter (optional).
    // The ID can be found in the documentation:
    //   https://developers.google.com/adwords/api/docs/appendix/languagecodes
    // Note: As of v201302, only a single language parameter is allowed.
    $languageParameter            = new LanguageSearchParameter();
    $english                      = new Language();
    $english->id                  = 1001;
    $languageParameter->languages = array( $english );

    $locationParameter            = new LocationSearchParameter();
    $germany                      = new Location();
    $germany->id                  = 2276;
    $locationParameter->locations = array( $germany );

    // Create related to query search parameter.
    $relatedToQuerySearchParameter          = new RelatedToQuerySearchParameter();
    $relatedToQuerySearchParameter->queries = array( $keyword );
    $selector->searchParameters[]           = $relatedToQuerySearchParameter;
    $selector->searchParameters[]           = $languageParameter;
    $selector->searchParameters[]           = $locationParameter;

    // Set selector paging (required by this service).
    $selector->paging          = new Paging( 0, AdWordsConstants::RECOMMENDED_PAGE_SIZE );
    $info_array['volume']      = null;
    $info_array['competition'] = null;
    do {
        // Make the get request.
        ////$page = $targetingIdeaService->get($selector);
        $page = $targetingIdeaService->get( $selector );


        // Display results.
        if (isset( $page->entries )) {
            foreach ($page->entries as $targetingIdea) {
                $data          = MapUtils::GetMap( $targetingIdea->data );
                $keyword       = $data['KEYWORD_TEXT']->value;
                $search_volume = isset( $data['SEARCH_VOLUME']->value ) ? $data['SEARCH_VOLUME']->value : 0;
                //$targeted_monthly_searches = isset($data['TARGETED_MONTHLY_SEARCHES']->value) ? $data['TARGETED_MONTHLY_SEARCHES']->value : 0;
                $competition = isset( $data['COMPETITION']->value ) ? $data['COMPETITION']->value : 0;
                $avg_cpc     = isset( $data['AVERAGE_CPC']->value ) ? $data['AVERAGE_CPC']->value->microAmount : 0;
                /* $categoryIds = implode(', ', $data['CATEGORY_PRODUCTS_AND_SERVICES']->value);
                printf("Keyword idea with text '%s', category IDs (%s) and average " . "monthly search volume '%s' was found.\n", $keyword, $categoryIds, $search_volume);*/

                printf( "Keyword with text '%s', Average CPC '%s' average " . "monthly search volume '%s' and COMPETITION '%s' was found.\n", $keyword, $avg_cpc, $search_volume, $competition );
                echo "<br/><br/><br/>";
                $info_array['volume']      = $search_volume;
                $info_array['competition'] = $competition;
            }
        } else {
            print "No keywords ideas were found.\n";
            echo "<br/><br/><br/>";
        }

        // Advance the paging index.
        $selector->paging->startIndex += AdWordsConstants::RECOMMENDED_PAGE_SIZE;
    } while ($page->totalNumEntries > $selector->paging->startIndex);

    return $info_array;
}
