<?php
class lightningimport_searchhelper
{
    public static function lightningimport_GetDropDownList($index, $filter)
    {
        global $wpdb;
        error_log('Filter parameter is: ' . print_r($filter, true));
        $filterArr = explode(',', $filter);
        $query = "select distinct f%d as %s from lightningimport_product_attributes spa
			join $wpdb->posts p on spa.post_id = p.id
			where f%d is not null
			and p.post_status = 'publish'";
        if (is_array($filterArr)) {
            for ($i = 0; $i < count($filterArr); $i++) {
                $findex = $i + 1;
                $fvalue = $filterArr[$i];
                $query .= "and spa.f$findex = '$fvalue' ";
            }
        }
        $query .= ' order by f%d';
        error_log('Query is : ' . $query);
        $query = $wpdb->prepare($query, $index, 'f' . $index, $index, $index);
        $attributeValues = $wpdb->get_col($query);
        //echo $query;
        //var_dump($attributeValues);

        wp_send_json($attributeValues);
        wp_die();
        die();
    }

}
