<?php

class AjaxController extends BaseController
{

  const ERROR_INVALID_ID = "Invalid ID paramater";

  /**
   * @var string specifies the default action to be 'list'.
   */
  public $defaultAction='fullResponse';

  /**
   * @var array valid favorite classes
   */
  private $favoriteWhitelist = array('favoriteTvShow', 'favoriteMovie', 'favoriteString');

  /**
   * @var array response data to be passed to the view
   */
  protected $response = array();

  /**
   * @return array action filters
   */
  public function filters()
  {
    return array(
      'accessControl', // perform access control for CRUD operations
    );
  }

  /**
   * Specifies the access control rules.
   * This method is used by the 'accessControl' filter.
   * @return array access control rules
   */
  public function accessRules()
  {
    return array(
/*      array('allow',  // allow all users
        'actions'=>array('fullResponse'),
        'users'=>array('*'),
      ), */
      array('allow', // allow authenticated user
        'actions'=>array(
            'fullResponse', 'dlFeedItem', 'saveConfig', 'addFeed', 'addFavorite', 'updateFavorite', 
            'inspect', 'clearHistory', 'createFavorite', 'deleteFavorite', 'loadFeedItems', 'resetData',
            'wizard', 'loadFavorite', 'getHistory',
        ),
        'users'=>array('@'),
      ),
      array('allow', // allow admin user 
        'actions'=>array(),
        'users'=>array('admin'),
      ),
      array('deny',  // deny all users
        'users'=>array('*'),
      ),
    );
  }

  public function loadFavorite($idString = null)
  {
    if($idString === null)
      $idString = $_GET['id'];
    list($class, $id) = explode('-', $idString);
    $class = substr($class, 0, -1);
    if(in_array($class, $this->favoriteWhitelist))
    {
      if(is_numeric($id))
        return CActiveRecord::model($class)->findByPk($id);
       elseif(empty($id))
        return new $class;
    }
    return false;
  }

  public function loadFeedItem($id = null)
  {
    if($id === null)
      $id = isset($_GET['feedItem_id']) ? $_GET['feedItem_id'] : null;

    if(is_numeric($id))
      return feedItem::model()->with('quality')->findByPk($id);

    $this->response['dialog']['error'] = true;
    $this->response['dialog']['content'] = self::ERROR_INVALID_ID;
    return false;
  }

  /**
   * Creates a new Favorite based off of a feed item
   */
  public function actionAddFavorite()
  {
    $this->response['dialog']['header'] = 'Add Favorite';

    $feedItem = $this->loadFeedItem();

    if($feedItem)
    {
      $fav = $feedItem->generateFavorite();
      $type=get_class($fav).'s';

      if($fav->save()) 
      {
        $this->response['dialog']['content'] = 'New favorite successfully saved';
        $htmlId = $type.'-'.$fav->id;
      }
      else
      {
        $this->response['dialog']['error'] = true;
        $this->response['dialog']['content'] = 'Failure saving new favorite';
        $this->response[$type.'-'] = $fav;
      }
      // After save to get the correct id
      $this->response['showFavorite'] = '#'.$type.'-'.$fav->id;
      $this->response['showTab'] = "#".$type;
    }

    $this->actionFullResponse();
  }

  protected function findFavoriteType() 
  {
    $class = null;
    foreach($this->favoriteWhitelist as $item) 
    {
      if(isset($_POST[$item])) 
        return $item;
    }

    $this->response['dialog']['error'] = true;
    $this->response['dialog']['content'] = 'Unknown favorite type';
    return false;
  }

  /**
   * updates a favorite based on $_POST data.  Called from action[Create|Update]Favorite
   * @param BaseFavorite the favorite to be updated
   */
  protected function updateFavorite($favorite)
  {
    $class = get_class($favorite);
    if(isset($_POST['quality_id']))
      $favorite->qualityIds = $_POST['quality_id'];

    $favorite->attributes = $_POST[$class];
    $favorite->save();
    // include data to display favorite in response
    $this->response['genresListData'] = CHtml::listData(genre::model()->findAll(), 'id', 'title');
    $this->response['feedsListData'] = CHtml::listData(feed::model()->findAll(), 'id', 'title');
    // get qualitys for use in forms and prepend a blank quality to the list
    $qualitys = quality::model()->findAll();
    $q = new quality;
    $q->title='';
    $q->id=-1;
    array_unshift($qualitys, $q);
    $this->response['qualitysListData'] = CHtml::listData($qualitys, 'id', 'title');
    // Tell the view to bring up the changed favorite
    $this->response[$class] = $favorite;
    $this->response['showFavorite'] = "#".$class.'s-'.$favorite->id;
    $this->response['showTab'] = "#".$class."s";
  }

  public function actionCreateFavorite()
  {
    $this->response = array('dialog'=>array('header'=>'Create Favorite'));
    $class = $this->findFavoriteType();
    if($class)
    {
      Yii::trace('creating favorite');
      $this->updateFavorite(new $class);
    }

    $this->actionFullResponse();
  }

  public function actionUpdateFavorite()
  {
    $this->response = array('dialog'=>array('header'=>'Update Favorite'));
    $class = $this->findFavoriteType();

    if($class && isset($_GET['id']) && is_numeric($_GET['id'])) 
    {
      Yii::trace('updating favorite');
      $model = new $class;
      $favorite = $model->findByPk($_GET['id']);
      if($favorite)
        $this->updateFavorite($favorite);
    }

    $this->actionFullResponse();
  }

  public function actionAddFeed()
  {
    $this->response['dialog']['header'] = 'Add Feed';
    if(isset($_POST['feed']))
    {
      $feed=new feed;
      $feed->attributes=$_POST['feed'];
      if($feed->save()) 
        $this->response['dialog']['content'] = 'Feed Added.  Status: '.$feed->statusText;
      else
      {
        $this->response['activeFeed-'] = $feed;
        $this->response['showTab'] = '#feeds';
      }
    }

    $this->actionFullResponse();

  }

  public function actionClearHistory()
  {
    history::model()->deleteAll();
    // no need to pass any variables, the history is now empty
    $this->render('history_dialog');
  }

  public function actionDeleteFeed()
  {
    $this->response['dialog']['header'] = 'Delete Feed';

    // Verify numeric input, dont allow delete of generic 'All' feeds(with !empty)
    if(!empty($_GET['id']) && is_numeric($_GET['id'])) {
      feed::model()->deleteByPk((integer)$_GET['id']);
      $this->response['dialog']['content'] = 'Your feed has been successfully deleted';
    }

    $this->actionFullResponse();
  }

  public function actionDlFeedItem()
  {
    $this->response['dialog']['header'] = 'Download Feed Item';
    
    $feedItem = $this->loadFeedItem();

    if($feedItem)
    {
      if(Yii::app()->dlManager->startDownload($feedItem, feedItem::STATUS_MANUAL_DL))
        $this->response['dialog']['content'] = $feedItem->title.' has been Started';
      else
      {
        $this->response['dialog']['error'] = true;
        $this->response['dialog']['content'] = CHtml::errorSummary(Yii::app()->dlManager);
      }
    } 

    $this->actionFullResponse();
  }

  public function actionFullResponse()
  {
    $app = Yii::app();
    $logger = Yii::getLogger();
    $startTime = microtime(true);
    $config = $app->dvrConfig;
    $time['dvrConfig'] = microtime(true);
    $favoriteMovies = favoriteMovie::model()->findAll(array('select'=>'id,name'));
    $time['favoriteMovies'] = microtime(true);
    $favoriteTvShows = favoriteTvShow::model()->with(array('tvShow'=>array('select'=>'id,title')))->findAll(array('select'=>'id'));
    $time['favoriteTvShows'] = microtime(true);
    $favoriteStrings = favoriteString::model()->findAll(array('select'=>'id,name'));
    $time['favoriteStrings'] = microtime(true);
    $feeds = feed::model()->findAll(); // todo: not id 0, which is 'All'
    $time['feeds'] = microtime(true);
    $availClients = $app->dlManager->availClients;
    $time['availClients'] = microtime(true);

    foreach($time as $key => $value) {
      $time[$key] = $value-$startTime;
      $startTime = $value;
    }

    Yii::log('Database timing '.print_r($time, true), CLogger::LEVEL_PROFILE);
    Yii::log("pre-render: ".$logger->getExecutionTime()."\n", CLogger::LEVEL_PROFILE);
    $this->render('fullResponse', array(
          'availClients'=>$availClients,
          'config'=>$config,
          'favoriteTvShows'=>$favoriteTvShows,
          'favoriteMovies'=>$favoriteMovies,
          'favoriteStrings'=>$favoriteStrings,
          'feeds'=>$feeds,
          'response'=>$this->response,
    ));
    Yii::log("end controller: ".$logger->getExecutionTime()."\n", CLogger::LEVEL_PROFILE);
  }

  public function actionGetHistory()
  {
    $this->render('history_dialog', array(
          'history'=>history::model()->findAll(array('order'=>'date DESC')),
    ));
  }

  public function actionInspect()
  {
    $view = 'inspectError';
    $item = null;
    $opts = array();

    $feedItem = $this->loadFeedItem();
    if($feedItem)
    {
      $opts['item'] = $feedItem;
      $record = $feedItem->itemTypeRecord;
      $view = 'inspect'.ucwords(get_class($record));
      $opts[get_class($record)] = $record;
    }
    $this->render($view, $opts);
  }

  public function actionLoadFavorite()
  {
    $favorite = $this->loadFavorite();
    if($favorite)
    {
      $genres = genre::model()->findAll();
      $feeds = feed::model()->findAll(); // todo: not id 0, which is 'All'
  
      // get qualitys for use in forms and prepend a blank quality to the list 
      $qualitys = quality::model()->findAll();
      $q = new quality;
      $q->title='';
      $q->id=-1;
      array_unshift($qualitys, $q);
     
      $this->render(get_class($favorite), array(
            'favorite'=>$favorite,
            'feedsListData'=>CHtml::listData($feeds, 'id', 'title'),
            'genresListData'=>CHtml::listData($genres, 'id', 'title'),
            'qualitysListData'=>CHtml::listData($qualitys, 'id', 'title'),
      ));
    }
  }
  public function actionLoadFeedItems()
  {
    $whiteList = array(
        'tv'=>'TV Episodes', 
        'movie'=>'Movies',
        'other'=>'Others',
        'queued'=>'Queued',
    );
    if(isset($_GET['type']) && in_array($_GET['type'], array_keys($whiteList)))
    {
      $type = $_GET['type'];
      $before = isset($_GET['before']) ? $_GET['before'] : null;
      $items = $this->prepareFeedItems($_GET['type'], $before);
      $this->render('feedItems_container', array(
        'type'  => $type,
        'name'  => $whiteList[$type],
        'items' => $items,
      ));
    }
  }

  public function actionResetData()
  {
    $whiteList = array('all', 'media', 'feedItems');
    $this->response = array('dialog'=>array('header'=>'Reset Data'));

    if(isset($_GET['type']) && in_array($_GET['type'], $whiteList))
    {
      $type = $_GET['type'];
      $transaction = Yii::app()->db->beginTransaction();
      try
      {
        switch($type)
        {
        case 'all':
          foreach(array('feedItem', 'feedItem_quality', 'history', 'movie', 'movie_genre', 'other', 'tvEpisode') as $class) 
          {
            $model = new $class;
            $model->deleteAll();
          }
          tvShow::model()->deleteAll('id NOT IN (SELECT tvShow_id from favoriteTvShows)');
          break;
        case 'media':
          movie::model()->updateAll(array('status'=>movie::STATUS_NEW));
          other::model()->updateAll(array('status'=>other::STATUS_NEW));
          tvEpisode::model()->updateAll(array('status'=>tvEpisode::STATUS_NEW));
          break;
        case 'feedItems':
          feedItem::model()->updateAll(array('status'=>feedItem::STATUS_NEW));
          break;
        }
        $transaction->commit();
      } 
      catch (Exception $e)
      {
        $transaction->rollback();
        throw $e;
      }

      if($type === 'all')
      {
        $feeds = feed::model()->findAll();
        foreach($feeds as $feed)
          $feed->updateFeedItems(False);
      }

      Yii::app()->dlManager->checkFavorites(feedItem::STATUS_NEW);
      $this->response['dialog']['content'] = 'Reset has been successfull';
    }
    $this->actionFullResponse();
  }
  public function actionSaveConfig()
  {
    $this->response = array('dialog'=>array('header'=>'Save Configuration'));

    $config = Yii::app()->dvrConfig;
    Yii::log(print_r($_POST, TRUE));
    if(isset($_POST['category']) && $config->contains($_POST['category']))
    {
      // empty dvrConfig allows still setting config client
      if(isset($_POST['dvrConfigCategory']))
        $config->$_POST['category']->attributes = $_POST['dvrConfigCategory'];

      // if this is a client category, also set the main config to use this client
      if(isset($_POST['type']) && in_array($_POST['type'], array('nzbClient', 'torClient')) &&
         substr($_POST['category'], 0, 6) === 'client')
        $config->$_POST['type'] = $_POST['category'];
    }
    elseif(isset($_POST['dvrConfig']))
    {
      $config->attributes = $_POST['dvrConfig'];
    }

    if($config->save()) 
    {
      $this->response['dialog']['content'] = 'Configuration successfully saved';
    }
    else
    {
      $this->response['dialog']['error'] = True;
      $this->response['dialog']['content'] = 'There was an error saving the configuration';
      $this->response['dvrConfig'] = $config;
      $this->response['showDialog'] = '#configuration';
    }

    $this->actionFullResponse();
  }

  public function actionWizard()
  {
    $this->response = array('dialog'=>array('header'=>'Initial Configuration', 'content'=>''));

    if(isset($_POST['dvrConfig']))
    {
      $config = Yii::app()->dvrConfig;
      $config->attributes = $_POST['dvrConfig'];
      $this->response['dialog']['content'] .= ($config->save() ? 'Saved configuration' : 'Failed saving configuration').'<br>';
    }

    if(isset($_POST['feed']))
    {
      $feeds = array();
      foreach(array('torUrl'=>feedItem::TYPE_TORRENT, 'nzbUrl'=>feedItem::TYPE_NZB) as $key => $type)
      {
        if(isset($_POST['feed'][$key]))
        {
          $feed = new feed;
          $feed->url = $_POST['feed'][$key];
          $feed->downloadType = $type;
          $this->response['dialog']['content'] .= ($feed->save() ? "Saved feed {$feed->title}" : "Failed saving feed {$feed->url}").'<br>';
       }
      }
    }

    if(empty($this->response['dialog']['content']))
      $this->response['dialog']['content'] = 'No valid attributes passed to wizard';
    $this->actionFullResponse();
  }

  private function prepareFeedItems($table, $before = null) 
  {
    $table = $table.'FeedItem';
    $group = 'feedItem_title';
    $attrs = 'feed_title, feedItem_status, feedItem_description, feedItem_id, feedItem_title, feedItem_pubDate ';

    $db = Yii::app()->db;
    $config = Yii::app()->dvrConfig;

    // First get a listing if the first group of items, and put them in an array indexed by title
    $db->createCommand(
        'CREATE TEMP TABLE prepareItems AS '.
        'SELECT '.$attrs.
        '  FROM '.$table.
        ($before === null ? '': ' WHERE feedItem_pubDate < '.$before).
        ' LIMIT '.($config->webItemsPerLoad*2)
    )->execute();
    $reader = $db->createCommand('SELECT * FROM prepareItems')->queryAll();
    $items = array();
    foreach($reader as $row) 
    {
      $items[$row[$group]][] = $row;
    }
    // Then get a listing with a group by clause on the title to get distinct titles, and a count to let us know when
    // to look for extras in the first array
    $sql= 'SELECT count(*) as count, '.$attrs.
          '  FROM prepareItems'.
          ' GROUP BY '.$group.
          ' ORDER BY feedItem_pubDate DESC'.
          ' LIMIT '.$config->webItemsPerLoad;
    $reader = $db->createCommand($sql)->queryAll();
    $output = array();
    foreach($reader as $row) 
    {
      if($row['count'] == 1)
        $output[] = $row;
      else {
        // use reference to prevent making aditional copy of array on sort
        $data =& $items[$row[$group]];
        usort($data, array($this, 'cmpItemStatus'));
        $output[] = $data;
      }
    }
    $db->createCommand('DROP TABLE prepareItems;')->execute();
    return $output;
  }

  public function cmpItemStatus($a, $b) {
    return($a['feedItem_status'] < $b['feedItem_status']);
  }

  public function actionDeleteFavorite() {
    $this->response = array('dialog'=>array('header'=>'Delete Favorite'));

    if(isset($_GET['id'], $_GET['type']) && is_numeric($_GET['id']) && in_array($_GET['type'], $this->favoriteWhitelist))
    {
      $id = (integer)$_GET['id'];
      $class = $_GET['type'];
      $model = new $class; // verified safe by whitelist
      Yii::log("deleting $class $id");

      // this logic might be better served in BaseFavorite somehow
      // Have to get the matching information before deleting the row
      // Is casting id to integer enough to make it safe without bindValue?
      $sql = "SELECT feedItem_id FROM matching${class}s WHERE ${class}s_id = $id AND feedItem_status NOT IN".
                  "('".feedItem::STATUS_AUTO_DL."', '".feedItem::STATUS_MANUAL_DL."');";
  
      $reader = Yii::app()->db->CreateCommand($sql)->queryAll();
      $ids = array();
      foreach($reader as $row) 
      {
        $ids[] = $row['feedItem_id'];
      }
   
      if($model->deleteByPk($id))
      {
        // Reset feedItem status on anything this was matching, then rerun matching routine incase something else matches the reset items
        feedItem::model()->updateByPk($ids, array('status'=>feedItem::STATUS_NEW));
        Yii::app()->dlManager->checkFavorites(feedItem::STATUS_NEW);
        $this->response['dialog']['content'] = 'Your favorite has been successfully deleted';
      } 
      else 
      {
        $this->response['dialog']['content'] = 'That favorite does not exist ?';
        $this->response['dialog']['error'] = True;
      }
    }

    $this->actionFullResponse();
  }

}
