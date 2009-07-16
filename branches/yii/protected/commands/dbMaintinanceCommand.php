<?php

class dbMaintinanceCommand extends BaseConsoleCommand {
  public function run($args) {
    $db = Yii::app()->db;

    $querys = array(
        //  Delete items more than the configured  days old
        'DELETE FROM feedItem'.
        ' WHERE feedItem.pubDate < '.(time()-(3600*24*Yii::app()->dvrConfig->feedItemLifetime)).';',
        // Delete others that dont point to a feed item anymore
        // Unless they have been marked downloaded, so it doesn't download same title in future
        'DELETE FROM other'.
        ' WHERE id NOT IN (SELECT other_id FROM feedItem)'.
        '   AND status = '.other::STATUS_NEW.';',
        // Delete movies that dont point to a feed item anymore
        // Unless they have been marked downloaded, so it doesn't download same title in future
        'DELETE FROM movie'.
        ' WHERE id NOT IN (SELECT movie_id FROM feedItem)'.
        '   AND status = '.movie::STATUS_NEW.';',
        // Delete tvShows that dont point to a feed item(indirectly) or a favorite
        'DELETE FROM tvShow'.
        ' WHERE id NOT IN (SELECT tvShow_id FROM tvEpisode WHERE id IN (SELECT tvEpisode_id FROM feedItem))'.
        '   AND id NOT IN (SELECT tvShow_id FROM favoriteTvShows);',
        // Delete tvEpisodes that point to a nonexistant tvShow or nonexistant feedItems
        // note: due to the above sql, the tvShow should only be nonexistant when no feedItems as well
        //       but we can have a tvShow and no feedItems so maybee only select on not in feedItem ?
        'DELETE FROM tvEpisode'.
        ' WHERE ( tvShow_id NOT IN (SELECT id FROM tvShow)'.
        '         OR'.
        '         id NOT IN (SELECT tvEpisode_id FROM feedItem)'.
        '       )'.
        '   AND status = '.tvEpisode::STATUS_NEW.';',
        // Empty out unrelated networks
        'DELETE FROM network'.
        ' WHERE id NOT IN (SELECT network_id FROM tvShow);',
        // Empty out unrelated genres
        'DELETE FROM genre'.
        ' WHERE id NOT IN (SELECT genre_id from favoriteMovies)'.
        '   AND id NOT IN (SELECT genre_id from movie_genre);',
        //
    );
    // SQLite doesn't do anything with foreign keys, so clean out MANY_MANY relationships that point to
    // non-existant things
    $pruneFk = array(
         array( 'table'   => 'feedItem_quality',
                'fk'      => 'feedItem_id',
                'pktable' => 'feedItem',
         ),
         array( 'table'   => 'favoriteMovies_quality',
                'fk'      => 'favoriteMovies_id',
                'pktable' => 'favoriteMovies',
         ),
         array( 'table'   => 'favoriteStrings_quality',
                'fk'      => 'favoriteStrings_id',
                'pktable' => 'favoriteStrings',
         ),
         array( 'table'   => 'favoriteTvShows_quality',
                'fk'      => 'favoriteTvShows_id',
                'pktable' => 'favoriteTvShows',
         ),
         array( 'table'   => 'tvShow_genre',
                'fk'      => 'tvShow_id',
                'pktable' => 'tvShow',
         ),
         array( 'table'   => 'movie_genre',
                'fk'      => 'movie_id',
                'pktable' => 'movie',
         ),
    );
    // could bind params instead with a custom createCommand(), but this is simple and straightforward, no chance of injection attack
    // and its not run interactively so the little bit of extra speed is negligable
    foreach($pruneFk as $item)
      $querys[] = "DELETE FROM {$item['table']} WHERE {$item['fk']} NOT IN (SELECT id FROM {$item['pktable']});";

    // delete more than maxItemsPerFeed
    $reader = $db->createCommand('SELECT id FROM feed')->query();
    foreach($reader as $row)
    {
      $querys[] =
        'DELETE FROM feedItem'.
        ' WHERE id IN ( SELECT id FROM feedItem'.
                      '  WHERE feed_id = '.$row['id'].
                      '  ORDER BY pubDate'.
                      '  LIMIT -1'.
                      ' OFFSET '.Yii::app()->dvrConfig->maxItemsPerFeed.
                      ');';
    }

    foreach($querys as $sql)
      $db->createCommand($sql)->execute();
  }
}
