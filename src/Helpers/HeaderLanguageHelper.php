<?php

namespace App\Helpers;

// This helper retrieves accept-language header, loops through its values and check if there's English or
// Arabic included. Returns English as default language.

class HeaderLanguageHelper 
{

    static public function match ($request) {

        // Remove any ";"
        if($request->getHeader('Accept-Language') != null)
        {
            $accept_languages = str_replace(";",",",$request->getHeader('Accept-Language')[0]);
            // Remove any spaces
            $accept_languages = str_replace(" ","",$accept_languages);
            // Convert to array
            $accept_languages = explode(",", $accept_languages);
    
            // Search for language and return
            foreach ($accept_languages as $key => $row) {
                
                if($row == "en" || $row == "ar" || $row == "fr")
                {  
                    return $row;
                }
            }
        }

        // Return english as default
        return "en";
    }
}
