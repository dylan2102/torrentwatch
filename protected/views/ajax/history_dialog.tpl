<?php $h = False; ?>
<div id="history" class="dialog_window">
 <?php if(!empty($history)): ?>
  <ul>
   <?php $n=0;foreach($history as $hItem): ?>
    <li <?php echo ++$n%2?'class="alt"':''; ?>>
      <div class="date"><?php echo date('Y M d h:i a', $hItem->date); ?></div>
      <a href="#histItem<?php echo $hItem->id; ?>">
          <?php echo $hItem->feedItem_title; ?>
      </a>
      <div class="hItemDetails" id="histItem<?php echo $hItem->id; ?>">
        <div class="histFav"><?php echo $hItem->favorite_name; ?></div>
        <div class="histFeed"><?php echo $hItem->feed_title; ?></div>
      </div>
    </li>
   <?php endforeach; ?>
  </ul>
 <?php endif; ?>
 <div class="buttonContainer">
   <?php echo CHtml::link('Clear', array('clearHistory'), array('class'=>'ajaxSubmit button', 'id'=>'clearHistory')); ?>
   <a class="toggleDialog button" href="#">Close</a>
 </div>
</div>
