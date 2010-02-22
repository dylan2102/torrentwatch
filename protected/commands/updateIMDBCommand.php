<?php

class updateIMDbCommand extends BaseConsoleCommand {

  protected $factory;

  public function run($args) {
    $this->factory = Yii::app()->modelFactory;
    $transaction = Yii::app()->db->beginTransaction();
    try {
      $this->updateMovies();
      // EXPERIMENTAL
      $this->updateOthers();
      $transaction->commit();
    } catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
  }

  protected function updateOthers() {
    $db = Yii::app()->db;
    $scanned = $toSave = array();
    $reader = $db->createCommand('SELECT id, title'.
                                 '  FROM other'.
                                 ' WHERE lastImdbUpdate = 0'
    )->queryAll();
    foreach($reader as $row) {
      $scanned[] = $row['id'];
      $title = $row['title'];
      if(substr($title, -4) === '1080')
        $title = substr($title, 0, -4);

      echo "Searching IMDb for $title\n";
      $scraper = new IMDbScraper($title);

      // maybee it has a prefix
      if($scraper->accuracy < 75  &&
         false !== ($pos = strpos($title, '-')))
      {
        $scraper = new IMDbScraper(substr($title, $pos+1));
      }
      // maybee there are some bs numbers at the begining
      if($scraper->accuracy < 75 &&
         $title !== ($tmpTitle = preg_replace('/^\d+\.?/', '', $title)))
      {
        $scraper = new IMDbScraper($tmpTitle);
      }

      if($scraper->accuracy < 75)
      {
        $scanned[] = $row['id'];
        Yii::log("Failed scrape of $title\n", CLogger::LEVEL_INFO);
        echo "Failed scrape of $title with accuracy of {$scraper->accuracy} and a guess of {$scraper->title}\n";
      }
      else
      {
        echo "Found! Updating to ".$scraper->title."\n";
        $toSave[] = array($row['id'], $row['title'], $movie, $scraper);
      }
    }

    $transaction = Yii::app()->db->beginTransaction();
    try {
      foreach($toSave as $arr) {
        list($id, $title, $scraper) = $arr;

        $movie = $this->factory->movieByImdbId($scraper->imdbId, $title);

        feedItem::model()->updateAll(
            array('other_id'=>NULL,
                  'movie_id'=>$movie->id,
            ),
            'other_id = '.$id
        );
        other::model()->deleteByPk($id);
        $this->updateMovieFromScraper($movie, $scraper);
      }
      if(count($scanned))
        other::model()->updateByPk($scanned, array('lastImdbUpdate'=>time()));
      $transaction->commit();
    } catch ( Exception $e ) {
      $transaction->rollback();
      throw $e;
    }
  }

  protected function updateMovies() {
    $db = Yii::app()->db;
    $now = time();
    $scanned = $toSave = array();
    $reader = $db->createCommand('SELECT id, imdbId'.
                                 '  FROM movie'.
                                 ' WHERE lastImdbUpdate <'.($now-(3600*24)). // one update per 24hrs
                                 '   AND imdbId IS NOT NULL'.
                                 '   AND rating IS NULL;'

    )->queryAll();
    foreach($reader as $row) {
      $scanned[] = $row['id'];
      
      echo "Looking for Imdb Id: ".$row['imdbId']."\n";
      $url = sprintf('http://www.imdb.com/title/tt%07d/', $row['imdbId']);
      $scraper = new IMDbScraper('', $url);

      if($scraper->accuracy < 75) {
        echo "Failed scrape\n";
        continue;
      }

      echo "Found! Updating ".$scraper->title."\n";
      $toSave[$row['id']] = $scraper;
    }

    $transaction = Yii::app()->db->beginTransaction();
    try {
      foreach($toSave as $id => $scraper)
        $this->updateMovieFromScraper($id, $scraper);

      if(count($scanned))
        movie::model()->updateByPk($scanned, array('lastImdbUpdate'=>$now));
      $transaction->commit();
      echo 'Saved '.count($toSave).' items'."\n";
    } catch ( Exception $e) {
      $transaction->rollback();
      throw $e;
    }
  }

  protected function updateMovieFromScraper($movie, $scraper)
  {
    if(!is_a($movie, 'movie'))
      $movie = movie::model()->findByPk($movie);

    $movie->year = $scraper->year;
    $movie->name = $scraper->title;
    $movie->runtime = $scraper->runtime;
    $movie->plot = $scraper->plot;
    $movie->rating = strtok($scraper->rating, '/');
    $movie->imdbId = $scraper->imdbId;
    if($movie->save()) {
      if(is_array($scraper->genres)) {
        foreach($scraper->genres as $genre) {
          $record = new movie_genre;
          $record->movie_id = $movie->id;
          $record->genre_id = $this->factory->genreByTitle($genre)->id;
          $record->save();
        }
      }
      return True;
    } else
      Yii::log('Error saving movie after IMDB update.', CLogger::LEVEL_ERROR);

    return False;
  }
}

