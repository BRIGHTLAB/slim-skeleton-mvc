<?php

namespace App\Helpers;


class PaginationHelper 
{

    static public function WrapPrevNextPages ($url,$request,$data,$count) {
        
    	$current_page = $request->getAttribute('PAGINATION_PAGE');
    	$next_page = (int) $request->getAttribute('PAGINATION_PAGE') + 1;
    	$previous_page = (int) $request->getAttribute('PAGINATION_PAGE') - 1;

        $paginated_data = [];

        $params_array = $request->getQueryParams();
        $page_query_param = is_null($request->getQueryParams('page')) ? (int) $current_page : $request->getQueryParams('page');
        $limit_query_param = is_null($request->getQueryParams('limit')) ? (int) $request->getAttribute('PAGINATION_LIMIT') : $request->getQueryParams('limit');
        //$offset = isset($query_params['offset']) ? (int) $query_params['offset'] )
        $offset_query_param = is_null($request->getQueryParams('offset')) ? (int) $request->getAttribute('PAGINATION_OFFSET') : $request->getQueryParams('offset');
        $paginated_data["total"] = $count;
        $paginated_data["page"] = $current_page;

     
        $allowed_params = ["limit", "offset"];
        $tmp = [];
        foreach ($params_array as $key => $row) {
            if(!in_array($key, $allowed_params))
                continue;
            $tmp[] = $key . "=" . $row;
        }
        $params = "&" . implode("&", $tmp);
        
        // Dont show previous if this is the first page
        if($current_page == 1)
        	$paginated_data["previous"] = "";
        else
        	$paginated_data["previous"] = $url . "?page=" . $previous_page . $params;


        // Check how many data has been shown
        $data_count = (( (int) $page_query_param - 1 ) * (int) $limit_query_param) + count($data);
        // Check if there is next
        if($data_count != $count)
            $paginated_data["next"] = $url . "?page=" . $next_page . $params;
        else
             $paginated_data["next"] = "";
        

        $paginated_data["data"] = $data;
        
        return $paginated_data;
    }
}
