#TODO#

##Bugs##

* Use is_singular instead of is_single

##Fixes##

* In some cases could be this warning:

Warning: strpos() expects parameter 1 to be string, array given in related-posts-thumbnails/related-posts-thumbnails.php on line 243

Warning: strpos() expects parameter 1 to be string, array given in related-posts-thumbnails/related-posts-thumbnails.php on line 244

* Don't show top text if there is no thumbnails

* In some cases could be this warning:

Warning: in_array() [function.in-array]: Wrong datatype for second argument in related-posts-thumbnails.php on line 379

* Use last N months instead of fixed start date

##Features##

* Customizable shortcode

* Exclude categories option

* Add an option for parsing/not parsing special characters in title (default not parsing - security reason)

* Integration with qTranslate

* Order by date option

##Changes##

* Only related posts from specified subcategories, not their super categories (explore if this can have performance drawback)

* Make List output style as default (support old installations)