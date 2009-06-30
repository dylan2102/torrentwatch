<?php

class feedItem extends ARwithQuality
{

  // Higher numbers so they can be sorted as "better" matches
  const STATUS_NEW = 0;
  const STATUS_NOMATCH = 1;
  const STATUS_MATCH = 2;
  const STATUS_FAILED_DL = 5;
  const STATUS_DUPLICATE = 6;
  const STATUS_OLD = 7;
  const STATUS_QUEUED = 15;
  const STATUS_AUTO_DL = 20;
  const STATUS_MANUAL_DL = 21;

  // Download Types
  const TYPE_TORRENT = 0;
  const TYPE_NZB = 1;

  /**
   * Returns the static model of the specified AR class.
   * @return CActiveRecord the static model class
   */
  public static function model($className=__CLASS__)
  {
    return parent::model($className);
  }

  /**
   * @return string the associated database table name
   */
  public function tableName()
  {
    return 'feedItem';
  }

  /**
   * @return array validation rules for model attributes.
   */
  public function rules()
  {
    return array(
      array('status, pubDate, lastUpdated, hash, downloadType, feed_id', 'required'),
      array('pubDate, lastUpdated, feed_id, movie_id, other_id, tvEpisode_id', 'numerical', 'integerOnly'=>true, 'min'=>0),
      array('status', 'numerical', 'integerOnly'=>true, 'min'=>self::STATUS_NEW, 'max'=>self::STATUS_MANUAL_DL),
    );
  }

  /**
   * @return array relational rules.
   */
  public function relations()
  {
    return array(
        'feed'=>array(self::BELONGS_TO, 'feed', 'feed_id'),
        'quality'=>array(self::MANY_MANY, 'quality', 'feedItem_quality(feedItem_id, quality_id)'),
        // Belongs to only one of the next 3
        'tvEpisode'=>array(self::BELONGS_TO, 'tvEpisode', 'tvEpisode_id'),
        'movie'=>array(self::BELONGS_TO, 'movie', 'movie_id'),
        'other'=>array(self::BELONGS_TO, 'other', 'other_id'),
    );
  }

  /**
   * @return array customized attribute labels (name=>label)
   */
  public function attributeLabels()
  {
    return array(
    );
  }

  /**
   * all valid download types and their string mappings
   * @return array number=>string pairs 
   */
  public function getDownloadTypeOptions() {
    return array(
        self::TYPE_TORRENT=>'Torrent',
        self::TYPE_NZB=>'NZB',
    );
  }

  /**
   * @return string String representation of download type
   */
  public function getDownloadTypeText() {
    $options=$this->getDownloadTypeOptions();
    return isset($options[$this->downloadType]) ? $options[$this->downloadType]
        : "unknown ({$this->downloadType})";
  }

  /**
   * all valid statuses and their string mappings
   * @return array number=>string pairs
   */
  public static function getStatusOptions() {
    return array(
        self::STATUS_AUTO_DL=>'Automatic Download',
        self::STATUS_DUPLICATE=>'Duplicate Episode',
        self::STATUS_FAILED_DL=>'Failed Download',
        self::STATUS_NEW=>'New',
        self::STATUS_NOMATCH=>'Unmatched',
        self::STATUS_MANUAL_DL=>'Manual Download',
        self::STATUS_MATCH=>'Matched',
        self::STATUS_OLD=>'Old Episode',
        self::STATUS_QUEUED=>'Queued for User',
    );
  }

  // static to allow translation directly from query row in a view without AR model
  public static function getStatusText($status = null) {
    if($status === null)
      $status = $this->status;
    $options=self::getStatusOptions();
    return isset($options[$status]) ? $options[$status]
        : "unknown ({$status})";
  }
      
  public function beforeValidate($type) {
    $this->lastUpdated = time();

    if($this->isNewRecord) {
      $this->status = self::STATUS_NEW;
      
      if($options = $this->detectTitleParams()) {
        list($shortTitle, $quality, $season, $episode) = $options;
     
        // Set the quality ids, creating them as necessary if they dont already exist
        $qualityIds = array();
        foreach($quality as $item) {
          $record = factory::qualityByTitle($item);
          $qualityIds[] = $record->id;
        }
        $this->qualityIds = $qualityIds;
    
        if(!is_numeric($season)) {
          // Item is a date based episode
          $episode = strtotime(str_replace(' ', '/', $season));
          Yii::log("Converting $season into $episode", CLogger::LEVEL_ERROR);
          if($episode === False) {
            $shortTitle .= ' '.$season;
            $episode = 0;
          }
          $season = 0;
  
        }
       
        if($season == 0 && $episode == 0) {
          // This is either movie or other
          // the fact of having imdbId isn't best differentiator
          if($this->imdbId > 1000) {
            $movie = factory::movieByImdbId($this->imdbId, $shortTitle);
            $this->movie_id = $movie->id;
          } else {
            $other = factory::otherByTitle($shortTitle);
            $this->other_id = $other->id;
          }
        } else {
          // Found a season and episode for this item
          $tvEpisode = factory::tvEpisodeByEpisode($shortTitle, $season, $episode);
          $this->tvEpisode_id = $tvEpisode->id;
        }
      } else
        Yii::log('Failed to detect for title: '.$this->title, CLogger::LEVEL_ERROR);
    }
    return parent::beforeValidate($type);
  }

  protected function detectTitleParams() {
    // strtr values
    $from = "._";
    $to = "  ";
    // Series Title
    $title_reg =
           '^([^-\(]+)' // Series title: string not including - or (
          .'(?:.+)?'; // Episode title: optinal, length is determined by the episode match
    // Episode
    $episode_reg =
           '\b('  // must be a word boundry before the episode to prevent turning season 13 into season 3
          .'S\d+[. _]?E\d+'.'|'  // S12E1 or S1.E22 or S4 E1
          .'\d+x\d+' .'|'  // 1x23
          .'\d+[. ]?of[. ]?\d+'.'|'  // 03of18
          .'[\d -.]{10}'   // 2008-03-23 or 07.23.2008 or .20082306. etc
          .')';
    $episode_reg2 = '\b(\d\d\d)\b.+'; // three digits (four hits movie years) with a word boundry on each side, ex: some.show.402.hdtv
                                      // with at least some data after it to not match a group name at the end
  
    // Possible Qualitys
    $qual_reg ='(DVB' .'|'
             .'720p'   .'|'
             .'DSR(ip)?|'
             .'DVBRip'  .'|'
             .'DVDR(ip)?|'
             .'DVDScr'  .'|'
             .'HR.HDTV' .'|'
             .'HDTV'    .'|'
             .'HR.PDTV' .'|'
             .'PDTV'    .'|'
             .'SatRip'  .'|'
             .'SVCD'    .'|'
             .'TVRip'   .'|'
             .'WebRip'  .'|'
             .'WS'      .'|'
             .'1080i'   .'|'
             .'1080p'   .'|'
             .'DTS'     .'|'
             .'AC3'     .'|'
             .'internal'.'|'
             .'limited' .'|'
             .'proper'  .'|'
             .'repack'  .'|'
             .'subbed'  .'|'
             .'x264'    .'|'
             .'Blue?Ray)';
 
    $quality = array('Unknown');
    if(preg_match_all("/$qual_reg/i", $this->title, $qregs)) {
      $q = array_change_key_case(array_flip($qregs[1]));
      // if 720p and hdtv strip hdtv to make hdtv more unique
      if(isset($q['720p'], $q['hdtv'])) {
        unset($qregs[1][$q['hdtv']]);
      }
      $quality = $qregs[1];
    }

    if(preg_match("/$title_reg$episode_reg/i", $this->title, $regs)) {
      $episode_guess = trim($regs[2]);
      $shortTitle = trim($regs[1]);
      $episode_guess = trim(strtr($episode_guess, $from, $to));
      // if match was a date season will receive it, guaranteed no x in the date from previous regexp so episode will be empty
      list($season,$episode) = explode('x', preg_replace('/(S(\d+) ?E(\d+)|(\d+)x(\d+)|(\d+) ?of ?(\d+))/i', '\2\4\6x\3\5\7', $episode_guess));
    } elseif(preg_match("/$title_reg$episode_reg2/i", $this->title, $regs)) {
      $shortTitle = trim($regs[1]);
      $episode_guess = $regs[2];
      $episode = substr($episode_guess, -2);
      $season = ($episode_guess-$episode)/100;
    } else {
      // No match, just strip everything after the quality
      $shortTitle = preg_replace("/$qual_reg.*/i", "", $this->title);
      $season = $episode = 0;
    }
    // Convert . and _ to spaces, and trim result
    $shortTitle = trim(strtr(str_replace("'", "&#39;", $shortTitle), $from, $to));

    return array($shortTitle, $quality, $season, $episode);
  }

  // Called from feedAdapter and extending classes to create new feed items
  public static function factory($data) {
    $item = new feedItem;
    $item->attributes = $data;
    if($item->save()) {
      return $item;
    } else {
      Yii::log("feedItem::factory() failed to create item\n".CHtml::errorSummary($item), CLogger::LEVEL_ERROR);
      return False;
    }
  }

}
