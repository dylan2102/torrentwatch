<?php

/**
 * movie_genre implements an AR of the many many relationship between
 * movie and genre
 * 
 * @uses CActiveRecord
 * @package nmtdvr
 * @version $id$
 * @copyright Copyright &copy; 2009-2010 Erik Bernhardson
 * @author Erik Bernhardson <journey4712@yahoo.com> 
 * @license GNU General Public License v2 http://www.gnu.org/licenses/gpl-2.0.txt
 */
class movie_genre extends CActiveRecord
{
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
    return 'movie_genre';
  }

  /**
   * @return array validation rules for model attributes.
   */
  public function rules()
  {
    return array(
      array('movie_id, genre_id', 'required'),
      array('movie_id, genre_id', 'numerical', 'integerOnly'=>true),
    );
  }

  /**
   * @return array relational rules.
   */
  public function relations()
  {
    return array(
    );
  }

  /**
   * @return array customized attribute labels (name=>label)
   */
  public function attributeLabels()
  {
    return array(
      'movie_id'=>'Movie ',
      'genre_id'=>'Genre ',
    );
  }
}
