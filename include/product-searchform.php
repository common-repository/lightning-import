<?php
ini_set('memory_limit', '256');
global $wpdb;
$spa_array_query = "select distinct ";
$spa_array_order = ' order by ';
$selects = "";
/*get the list of years*/
$wpdb->show_errors(true);
$productAttributeColumns = $wpdb->get_col('select attributename as `attributename` from lightningimport_product_attribute_mapping where ifnull(attributename,"") !="" order by columnorder;');
$productAttributeColumns = (array) $productAttributeColumns;
//var_dump($productAttributeColumns);
for ($i = 0; $i < count($productAttributeColumns); $i++) {
    $attributeName = $productAttributeColumns[$i];
    $dbi = $i + 1;
    $selects .= '<span>' . $attributeName . '</span><br />';
    $selects .= '<select id="f' . $dbi . '" name="f' . $dbi . '" style="width:100%" data-index="' . $dbi . '" class="disabled slnzattr">';
    $selects .= '<option value="--Select--">--Select--</option>';
    if ($i == 0) {
        $attributeValues = $wpdb->get_col($wpdb->prepare('select distinct f%d as %s from lightningimport_product_attributes where f%d is not null order by f%d', $dbi, $productAttributeColumns[$i], $dbi, $dbi));
        foreach ($attributeValues as $attributeValue) {
            $attributeValue = str_replace('\'', '', $attributeValue);
            $selects .= '<option value="' . $attributeValue . '">' . $attributeValue . '</option>';
        }
    }
    $selects .= '</select> <br/>';
}

$selects .= '<span class="spa_count" data-val="' . count($productAttributeColumns) . '"></span>';
$form = '<form role="search" method="get" id="searchform" action="' . esc_url(home_url('/')) . '">
	<div>
	<label class="screen-reader-text" for="s">' . __('Select ' . implode(',', $productAttributeColumns) . ':', 'woocommerce') . '</label>
	<a href="" id="clearDropDowns" name="clearDropDowns">Clear Selections</a>
	<br/>';
$form .= $selects;
$form .= '
	<span>Keyword</span><br />
	<input type="text" value="' . get_search_query() . '" name="s" id="s" placeholder="' . __('Enter your search term', 'woocommerce') . '" style="width:100%" />
	<br />
	<br />
	<input type="submit" id="searchsubmit" value="' . esc_attr__('Search', 'woocommerce') . '" style="width:100%" />
	<input type="hidden" name="post_type" value="product" />
	</div>
	</form>';

echo $form;
