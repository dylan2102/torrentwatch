<?php
/**
 * MainMenu is a widget displaying main menu items.
 *
 * The menu items are displayed as an HTML list. One of the items
 * may be set as active, which could add an "active" CSS class to the rendered item.
 *
 * To use this widget, specify the "items" property with an array of
 * the menu items to be displayed. Each item should be an array with
 * the following elements:
 * - visible: boolean, whether this item is visible;
 * - label: string, label of this menu item. Make sure you HTML-encode it if needed;
 * - url: string|array, the URL that this item leads to. Use a string to
 *   represent a static URL, while an array for constructing a dynamic one.
 * - pattern: array, optional. This is used to determine if the item is active.
 *   The first element refers to the route of the request, while the rest
 *   name-value pairs representing the GET parameters to be matched with.
 *   When the route does not contain the action part, it is treated
 *   as a controller ID and will match all actions of the controller.
 *   If pattern is not given, the url array will be used instead.
 */
class MainMenu extends CWidget
{
  public $items=array();
  public $resolution='hd';
  public $onKeyRight;

  public function run()
  {
    $items = array();
    $controller=$this->controller;
    $action=$controller->action;
    foreach($controller->mainMenuItems as $item)
    {
      if(isset($item['visible']) && !$item['visible'])
        continue;
      $outputItem=array();
      $outputItem['label']=$item['label'];
      if(is_array($item['url']))
        $outputItem['url']=$controller->createUrl($item['url'][0]);
      else
        $outputItem['url']=$item['url'];
      $pattern=isset($item['pattern'])?$item['pattern']:$item['url'];
      $outputItem['active']=$this->isActive($pattern,$controller->uniqueID,$action->id);
      $outputItem['name'] = isset($item['name'])?$item['name']:strtok($item['label'], ' ');
      $outputItem['onkeyleftset'] = isset($last)?$last['name']:$outputItem['name'];
      $outputItem['onkeyupset'] = isset($last)?$last['name']:$outputItem['name'];
      $items[]=$last=$outputItem;
    }
    $this->render('mainMenu_'.$this->resolution,array(
          'onKeyRight'=>$this->onKeyRight,
          'icon'=>'side_server.png',
          'imageRoot'=>$controller->imageRoot, 
          'items'=>$items,
          'itemStyle'=>'width:'.($controller->resolution === 'hd'?180:120),
    ));
  }

  protected function isActive($pattern,$controllerID,$actionID)
  {
    if(!is_array($pattern) || !isset($pattern[0]))
      return false;

    $pattern[0]=trim($pattern[0],'/');
    if(strpos($pattern[0],'/')!==false)
      $matched=$pattern[0]===$controllerID.'/'.$actionID;
    else
      $matched=$pattern[0]===$controllerID;

    if($matched && count($pattern)>1)
    {
      foreach(array_splice($pattern,1) as $name=>$value)
      {
        if(!isset($_GET[$name]) || $_GET[$name]!=$value)
          return false;
      }
      return true;
    }
    else
      return $matched;
  }
}
