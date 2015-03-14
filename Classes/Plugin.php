<?php

/**
 * Plugin class.
 */
namespace Phile\Plugin\StijnFlipper\PhilePaginator;

/**
 * Class Plugin
 *
 * @author  Stijn Wouters
 * @link    https://github.com/Stijn-Flipper/philePaginator
 * @license http://choosealicense.com/licenses/mit/
 * @package Phile\Plugin\StijnFlipper\PhilePaginator
 */
class Plugin extends \Phile\Plugin\AbstractPlugin implements \Phile\Gateway\EventObserverInterface
{

    /**
     * The paginator to be used
     *
     * @var function paginator
     */
    private $paginator = null;

    /**
     * The current offset
     *
     * @var int offset
     */
    private $offset = 0;

    /**
     * The requested uri
     *
     * @var string uri
     */
    private $uri = '';

    /**
     * The first page
     *
     * @var int first_page
     */
    private $first_page = 0;

    /**
     * Constructor.
     *
     * Register plugin to Phile Core.
     */
    public function __construct()
    {
        \Phile\Event::registerEvent('request_uri', $this);
    }

    /**
     * Process request.
     *
     * @return void
     */
    public function request($uri)
    {
        // set uri
        $this->uri = urldecode($uri);

        // set paginator (set to NULL if there's no such paginator for the
        // requested uri)
        $paginators = $this->settings['paginators'];
        $uri = '/'.$uri;
        $this->paginator = (array_key_exists($uri, $paginators)) ? $paginators[$uri] : null;

        // get first page
        $this->first_page = $this->settings['first_page'];

        // set page offset
        $key = $this->settings['url_parameter'];
        $match = array();
        preg_match('/\?'.$key.'=-?[0-9]+/', $_SERVER['REQUEST_URI'], $match);
        $this->offset = (empty($match)) ? 0 : intval(substr($match[0], strlen('?'.$key.'='))) - $this->first_page;

        // note that we're using $_SERVER['REQUEST_URI'] instead the usually
        // $_GET, this is because both seems to work in nginx as for Apache
        // ($_GET fails on some nginx servers with improper rewrite rules)
    }

    /**
     * Get all the posts to be paginated.
     *
     * @return array The set of posts to be paginated
     */
    public function getPosts()
    {
        // get all the posts
		$repo = new \Phile\Repository\Page($this->settings);
        $pages = $repo->findAll();

        // if there's no paginator provided, then simply return all the pages
        if (null === $this->paginator)
            return $pages;

        // otherwise, use the paginator to determine whether you should
        // paginate the given post
        return array_filter($pages, $this->paginator);
    }

    /**
     * Get all the paginated pages
     *
     * @return array A two dimensional array of posts
     */
    public function getPages()
    {
        // get max posts per page
        $posts_per_page = $this->settings['posts_per_page'];

        // get all the paginated posts
        $posts = $this->getPosts();

        // if max posts per page is less than 1, then simply return all the
        // posts
        if ($posts_per_page < 1)
            return array($posts);

        // otherwise break'em up in chunks of arrays
        return array_chunk($posts, $posts_per_page);
    }

    /**
     * Export template variables variables
     *
     * @return void
     */
    public function export()
    {
        // get template variables
        $registry = 'templateVars';
        $vars = (\Phile\Registry::isRegistered($registry)) ? \Phile\Registry::get($registry) : array();

        // get pages
        $pages = $this->getPages();
        $pages_count = count($pages) - 1;

        // get uri pattern for previous/next navigation
        $uri = $this->uri.'?'.$this->settings['url_parameter'].'=%s';

        // get index to current page
        $current = $this->offset + $this->first_page;

        // extend template variables
        $vars['paginator'] = array(
            'offset'   => $this->offset,
            'first'    => sprintf($uri, $this->first_page),
            'previous' => ($this->offset > 0) ? sprintf($uri, $current - 1) : '',
            'next'     => ($this->offset < $pages_count) ? sprintf($uri, $current + 1) : '',
            'last'     => sprintf($uri, $this->first_page + $pages_count),
            'pages'    => $this->getPages(),
        );
        \Phile\Registry::set($registry, $vars);
    }

    /**
     * Execute plugin.
     *
     * @param string $event
     * @param null $data
     *
     * @return void
     */
    public function on($event, $data=null)
    {
        if ('request_uri' === $event)
            $this->request($data['uri']);

        // always export variables
        $this->export();
    }

} // end class
