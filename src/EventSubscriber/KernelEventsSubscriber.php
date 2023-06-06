<?php

namespace Metabolism\WordpressBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function Env\env;

class KernelEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            RequestEvent::class => 'onKernelRequest',
            ResponseEvent::class => 'onKernelResponse',
        ];
    }
    
    
    /**
     * @param Request $request
     * @return void
     */
    private function fixServerVars(Request $request){
        
        if( empty($_SERVER['REQUEST_METHOD']??'') )
            $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        
        if( empty($_SERVER['REQUEST_URI']??'') )
            $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        
        if( empty($_SERVER['PHP_SELF']??'') )
            $_SERVER['PHP_SELF'] = $request->getScriptName();
        
        if( empty($_SERVER['PATH_INFO']??'') )
            $_SERVER['PATH_INFO'] = $request->getPathInfo();
    }
    
    /**
     * @param ControllerEvent $event
     * @return void
     * @throws \Exception
     */
    public function onKernelController(ControllerEvent $event)
    {
        if ( !$event->isMainRequest() )
            return;
        
        if( wp_is_maintenance_mode() || (function_exists('wp_maintenance_mode') && wp_maintenance_mode()) )
            throw new \Exception('Service Unavailable', 503);
    }
    
    
    /**
     * @param RequestEvent $event
     * @return void
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if( defined('REST_REQUEST') )
            return;
        
        $request = $event->getRequest();
        
        $this->fixServerVars($request);
        
        // using cli,
        if( is_multisite() && !is_admin() && php_sapi_name() == 'cli' ){
            
            $site = get_site_by_path( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
            
            if( $site && get_current_blog_id() != $site->blog_id )
                switch_to_blog($site->blog_id);
        }
        
        global $wp;
        
        $wp->init();

        if( $wp->parse_request() ){

            $wp->query_posts();
            $wp->register_globals();
        }

        do_action_ref_array( 'wp', array( &$wp ) );
        do_action( 'template_redirect' );
        do_action( 'kernel_loaded' );

        //Wordpress override $request, so restore it for Kernel shutdown
        global $request;
        $request = $event->getRequest();
    }
    
    
    /**
     * @param ResponseEvent $event
     * @return void
     * @throws \Exception
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        
        global $wpdb;
        
        $default_uri = php_sapi_name() == 'cli' ? env('DEFAULT_URI') : get_home_url();
        $default_uri = rtrim($default_uri, '/');

        $home_url = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", 'home' ) );
        
        $base_url = is_multisite() ? network_home_url() : $home_url->option_value;
        $base_url = rtrim($base_url, '/');

        if( $base_url != $default_uri ){

            $content = str_replace($base_url, $default_uri, $response->getContent());
            $content = str_replace(substr(json_encode($base_url), 1 , -1), substr(json_encode($default_uri), 1 , -1), $content);
            
            $response->setContent($content);
        }
    }
}
