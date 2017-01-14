  <tr>
  <th style="vertical-align: top; text-align: left; width:20%;" scope="row"><?php echo ucfirst($post_type); ?></th>
    <td style="width: 20%;">
      <input name="wub_post_type_option_<?php echo $post_type; ?>" class="wub_post_type_option" data-post_type="<?php echo $post_type; ?>" type="radio" value="default">Default<br>
      <?php
      foreach ($taxonomy_objects as $taxonomy) {

        if ($taxonomy->hierarchical && $taxonomy->show_ui) {
          ?>
          <input name="wub_post_type_option_<?php echo $post_type; ?>" class="wub_post_type_option" data-post_type="<?php echo $post_type; ?>"type="radio" value="<?php echo $taxonomy->name; ?>" ><?php echo ucfirst($taxonomy->labels->name); ?><br>
          <?php }
        } ?>
      </td>
    </tr>