<?php
namespace API;

use stdClass, Exception;

class McDonalds {
  /**
   * Request a list of McDonald's locations with the given parameters.
   *
   * Uses the public API's at mcdonalds.com. This requires an internet connection.
   * @param  array Optional list of constraints. This is used to narrow down the results.
   * @return [type]
   */
  public static function searchLocation(array $params = []) {
    $lat = isset($params['lat']) ? (string) $params['lat'] : '39';
    $lon = isset($params['lon']) ? (string) $params['lon'] : '-76';
    $radius = isset($params['radius']) ? (string) $params['radius'] : '2000000000';
    $maxResults = isset($params['maxResults']) ? (string) $params['maxResults'] : '2000000000';
    $country = isset($params['country']) ? (string) $params['country'] : 'us';
    $language = isset($params['language']) ? (string) $params['language'] : 'en-us';
    // Retrieve data from the McDonald's search location API.
    // We use an arbitartory location with a search radius of 2,000,000,000 miles. With the country set to "us", this should return all McDonald's locations in the United States.
    $query = [
      'method' => 'searchLocation',
      'latitude' => $lat,
      'longitude' => $lon,
      'radius' => $radius,
      'maxResults' => $maxResults,
      'country' => $country,
      'language' => $language,
    ];
    $queryString = http_build_query($query);
    $endpoint = 'https://www.mcdonalds.com/googleapps/GoogleRestaurantLocAction.do?';
    $url = $endpoint . $queryString;
    $data = file_get_contents($url);
    $data = json_decode($data, false);
    if (['features'] !== array_keys(get_object_vars($data))) {
      throw new Exception('Unexpected data returned from mcdonalds.com!');
    }
    $newData = [];
    foreach ($data->features as $feature) {
      if (['geometry', 'properties'] !== array_keys(get_object_vars($feature))) {
        throw new Exception('Unexpected data returned from mcdonalds.com! Geometry and properties are not the only properties in the feature.');
      }

      $geometry = $feature->geometry;
      if (['coordinates'] !== array_keys(get_object_vars($geometry))) {
        throw new Exception('Unexpected data returned from mcdonalds.com! Coordinates are not the only property in the feature\'s geometry.');
      }
      if (!is_array($geometry->coordinates)) {
        throw new Exception('Unexpected data returned from mcdonalds.com! Coordinates are not in an array.');
      }
      if (count($geometry->coordinates) !== 2) {
        throw new Exception('Unexpected data returned from mcdonalds.com! Coordinates do not have only 2 items.');
      }

      $newFeature = new stdClass();
      $newFeature->lon = $geometry->coordinates[0];
      $newFeature->lat = $geometry->coordinates[1];
      foreach ($feature->properties as $key => $value) {
        if ($key === 'identifiers') {
          if (['storeIdentifier', 'gblnumber'] !== array_keys(get_object_vars($value))) {
            throw new Exception('Unexpected data returned from mcdonalds.com! storeIdentifier and gblnumber are not the only properties in the feature\'s identifiers.');
          }
          if (!is_array($value->storeIdentifier)) {
            throw new Exception('Unexpected data returned from mcdonalds.com! Coordinates are not in an array.');
          }
          $idCount = 0;
          $siteIdNumber = null;
          $natlStrNumber = null;
          $regionId = null;
          $coop = null;
          $coopId = null;
          $tvMarket = null;
          $tvMarketId = null;
          foreach ($value->storeIdentifier as $identifier) {
            $idKey   = $identifier->identifierType;
            $idValue = $identifier->identifierValue;
            if ($idKey === 'SiteIdNumber' && $siteIdNumber === null) {
              $siteIdNumber = $idValue;
              $idCount++;
            } elseif ($idKey === 'NatlStrNumber' && $natlStrNumber === null) {
              $natlStrNumber = $idValue;
              $idCount++;
            } elseif ($idKey === 'Region ID' && $regionId === null) {
              $regionId = $idValue;
              $idCount++;
            } elseif ($idKey === 'Co-Op' && $coop === null) {
              $coop = $idValue;
              $idCount++;
            } elseif ($idKey === 'Co-Op ID' && $coopId === null) {
              $coopId = $idValue;
              $idCount++;
            } elseif ($idKey === 'TV-Market' && $tvMarket === null) {
              $tvMarket = $idValue;
              $idCount++;
            } elseif ($idKey === 'TV-Market ID' && $tvMarketId === null) {
              $tvMarketId = $idValue;
              $idCount++;
            } else {
              throw new Exception('Unexpected data returned from mcdonalds.com! Unknown identifier in feature.');
            }
          }
          if ($idCount !== 7) {
            throw new Exception('Unexpected data returned from mcdonalds.com! Missing identifiers in feature.');
          }
          $newFeature->siteIdNumber = $siteIdNumber;
          $newFeature->natlStrNumber = $natlStrNumber;
          $newFeature->regionId = $regionId;
          $newFeature->coop = $coop;
          $newFeature->coopId = $coopId;
          $newFeature->tvMarket = $tvMarket;
          $newFeature->tvMarketId = $tvMarketId;
          $newFeature->gblnumber = $value->gblnumber;
        } elseif (in_array($key, ['birthDaysParties', 'driveThru', 'outDoorPlayGround', 'indoorPlayGround', 'wifi', 'breakFast', 'nightMenu', 'giftCards', 'mobileOffers'])) {
          if ($value !== '0') {
            throw new Exception('Unexpected value from ' . $key . '!');
          }
        } elseif (in_array($key, ['jobUrl', 'longDescription', 'storeNotice'])) {
          if ($value !== '') {
            throw new Exception('Unexpected value from ' . $key . '!');
          }
        } else {
          $newFeature->$key = $value;
        }
      }
      $newData[] = $newFeature;
    }
    // Next, save this to a temporary file for later proccessing.
    return $newData; // return an object
  }
}
