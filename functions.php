<?php
add_theme_support('custom-logo');
add_theme_support('menus');

// Add site logo to rest api
add_action('rest_api_init', 'add_logo_to_JSON');

function add_logo_to_JSON()
{
  register_rest_field('page', 'site_logo_src', array( // post for where to register - page_logo_src is the name for api
    'get_callback'    => 'get_logo_src',
    'update_callback' => null,
    'schema'          => null,
  ));
}

function get_logo_src($object, $field_name, $request)
{
  $size = 'full';
  $custom_logo_id = get_theme_mod('custom_logo');
  $feat_img_array = wp_get_attachment_image_src($custom_logo_id, $size, true);
  return $feat_img_array[0];
}

// Add rebuild button to adminbar (top)
add_action('admin_bar_menu', 'add_item', 100);

function add_item($admin_bar)
{
  global $pagenow;
  $admin_bar->add_menu(array('id' => 'rebuild-frontend', 'title' => 'Rebuild Frontend', 'href' => '#'));
}

add_action('admin_footer', 'clickHandlerRebuildButton');

function clickHandlerRebuildButton()
{ ?>
  <script type="text/javascript">
    const rebuildBtn = document.querySelector(
      "#wp-admin-bar-rebuild-frontend .ab-item"
    );

    let rebuilding = false;

    rebuildBtn.addEventListener("click", (e) => {
      e.preventDefault();

      // Tiny bouncer
      if (rebuilding) return;

      rebuilding = true;
      let originalText = rebuildBtn.textContent;
      rebuildBtn.textContent = "Rebuilding...";

      let name = window.location.host.split(".")[0];
      fetch(`https://rebuilds.codebirds-apiserver.nl/${name}`, {
          method: "POST",
        })
        .then((response) => response.json())
        .then((data) => {
          //Handle your data
          if (data === "error")
            rebuildBtn.textContent = `${originalText} (last rebuild failed)`;
          else
            rebuildBtn.textContent = `${originalText} (last rebuild was successful)`;
          rebuilding = false;
        })
        .catch((err) => {
          console.log(err);
          rebuildBtn.textContent = `${originalText} (last rebuild failed)`;
        });

      let timeout = setTimeout(() => {
        rebuildBtn.textContent = originalText;
        clearTimeout(timeout);
      }, 1000 * 60);
    });
  </script>
<?php
}

// HEADLESS MODE CONFIGURATION
if (!defined('HEADLESS_MODE_CLIENT_URL')) {
  $host = $_SERVER['HTTP_HOST'];
  $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"], 0, 5)) == 'https' ? 'https' : 'http';
  $domain = $protocol . '://' . $host;
  define('HEADLESS_MODE_CLIENT_URL', $domain);
};

/**
 *
 * @see https://stackoverflow.com/a/768472/1469799
 *
 * @param $url
 * @param bool $permanent
 */
function headless_mode_redirect($url, $permanent = false)
{
  if (!HEADLESS_MODE_CLIENT_URL) {
    return;
  }

  header('Location: ' . $url, true, $permanent ? 301 : 302);

  exit();
}

/**
 * Based on https://gist.github.com/jasonbahl/5dd6c046cd5a5d39bda9eaaf7e32a09d
 */
add_action('parse_request', 'headless_mode_disable_front_end', 99);

function headless_mode_disable_front_end()
{
  if (current_user_can('edit_posts')) {
    return;
  }

  global $wp;

  /**
   * If the request is not part of a CRON, REST Request, GraphQL Request or Admin request,
   * output some basic, blank markup
   */
  if (
    !defined('DOING_CRON') &&
    !defined('REST_REQUEST') &&
    !is_admin() &&
    (empty($wp->query_vars['rest_oauth1']) &&
      !defined('GRAPHQL_HTTP_REQUEST'))
  ) {
    // adds the rest of the request to the new URL.
    $new_url = trailingslashit(HEADLESS_MODE_CLIENT_URL) . $wp->request;

    headless_mode_redirect($new_url, true);
    exit;
  }
}
