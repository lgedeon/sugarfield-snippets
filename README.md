Usage:

[sugarfield p="12345"]
Any post/page/cpt/snippet id. The content is posted as-is unless a template is provided.

[sugarfield slug/pagename="abcde" post_type="optional - default:snippet"]
Can use slug (consistent with front-end) or pagename (consistent with WP_Query).
Use post_type to disambiguate. Again, content as-is unless a template is provided.

[sugarfield query="12345"]
Any post/page/cpt id or snippet id/slug as long as its only content is query parameters stored as a json/php array 
Iterates through all query results outputing content of each or loading the_post and calling the template for each.

[sugarfield query="12345" template_snippet="23456"]
Snippet id/slug. Display results of query using template_snippet for formatting

[sugarfield template_snippet="23456"]
Display main loop using template_snippet for formatting

[sugarfield query="12345" template_part="slug" template_name="name"]
Calls get_template_part() for each query result

[sugarfield template_part="slug" template_name="name"]
Get template part or full template once without changing current post. Can get header.php, footer.php etc. too!

[sugarfield widget_area="sidebar-1"]
get a widget area

[sugarfield menu="34567"]
Desired menu. Accepts (matching in order) id, slug, name.

[sugarfield menu_location="abc-def"]
Theme location to be used. Must be registered with register_nav_menu() in order to be selectable by the user.

[sugarfield field="post.taxonomies.category"]
List all terms of the category that are associated with the given post.

[sugarfield field="taxonomy.category"]
List all terms of the category that are associated with the given post.





Settings Page
Comma separated list of menu locations
Comma separated list of widget areas
Comma separated list of supported post types
