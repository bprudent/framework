<?php

class Main
{
    /**
     * Starts a session.
     *
     * @return void
     */
    public static function startSession()
    {
        global $config;

        $path = $config->absUriPath;
        $path = (preg_match('/\/$/', $path) ? $path : "$path/");

        session_set_cookie_params($config->getSessionExpireTime(), $path);
        session_start();
    }

    /**
     * Parses the request and populates the $_GET and $_REQUEST arrays. The order of precedence is as follows:
     *
     * 1.) An ILifeCycle implementation handling onURLRewrite()
     * 2.) A matching key in $config->getUrlMappings()
     * 3.) The ILinkPolicy::parse() method
     *
     * @return void
     */
    public static function parseRequest()
    {
        global $config;

        $handledUrl = LifeCycleManager::onURLRewrite();
        if (!$handledUrl) {
            $mappings = $config->getUrlMappings();
            $url = preg_replace('|^' . $config->absUriPath . '[/]?|i', '', Params::server('REQUEST_URI'));
            if ($url) {
                foreach ($mappings as $key => $map) {
                    if ($key == $url) {
                        $_REQUEST[AppConstants::COMPONENT_KEY] = $_GET[AppConstants::COMPONENT_KEY] = $map[0];
                        $_REQUEST[AppConstants::ACTION_KEY] = $_GET[AppConstants::ACTION_KEY] = $map[1];
                        if (count($map) > 2) {
                            $sets = explode(',', 'null,' . $map[2]);
                            foreach ($sets as $set) {
                                if ($set == 'null') {
                                    continue;
                                }

                                $args = explode('=', $set);

                                $_REQUEST[$args[0]] = $_GET[$args[0]] = $args[1];
                            }
                        }

                        if (count($map) > 3) {
                            $_REQUEST[AppConstants::STAGE_KEY] = $_GET[AppConstants::STAGE_KEY] = $map[3];
                        }

                        $handledUrl = true;
                        break;
                    }
                }
            }

            if (!$handledUrl) {
                $policy = PolicyManager::getInstance();
                $policy->parse();
            }
        }
    }

    /**
     * populates the current ticket
     *
     * @return  void
     */
    public static function populateCurrent()
    {
        global $current, $config;

        $current = null;
        if (Session::get(AppConstants::LAST_CURRENT_KEY)) {
            $current = Application::popSavedCurrent();
        }
        else {
            /**
             * The current variable is the third of three global variables
             * in the application. This variable holds the current state
             * of the application such as the physical path, and messages
             * between the application and user.
             *
             * @global Current $current
             * @see Current;
             * @var Current
             */
            $current = new Current();
        }

        $current->id = Params::request('id', 0);

        $current->setSecureRequest(Params::request(AppConstants::SECURE_KEY));
        $componentClass = (Params::request(AppConstants::COMPONENT_KEY)
                              ? Params::request(AppConstants::COMPONENT_KEY)
                              : $config->getDefaultComponent());
        $current->component = Component::load($componentClass);
        if (!$current->component) {
            $config->error("Unknown component $componentClass");
            throw new Exception("Unknown component $componentClass");
        }

        $actionId = (Params::request(AppConstants::ACTION_KEY, null)
                               ? Params::request(AppConstants::ACTION_KEY, null)
                               : $config->getDefaultAction());
        $current->action = $current->component->getAction($actionId);
        if (!$current->action) {
            $message = "Unknown action $actionId";
            $config->error($message);
            throw new Exception($message);
        }

        if ((Params::server('HTTPS') != 'on') && $current->action->requiresSSL) {
            $uri = $current->getCurrentRequest(array('-secure'=>true));
            Application::forward($uri);
        }

        $current->stage = Params::request(AppConstants::STAGE_KEY, Stage::VIEW);
    }

    /**
     * Requires the -secure requests be secured via https by forwarding the request
     * to the https equivalent
     *
     * @return void
     */
    public static function secureRequest()
    {
        global $current;

        if (($current->isSecureRequest()) && (!Params::server('HTTPS'))) {
            $uri = $current->getCurrentRequest(array('-textalize' => true));

            Application::Forward($uri);
        }
    }

    /**
     * Enforces session timeouts
     *
     * @return void
     */
    public static function sessionTimeout()
    {
        global $config, $current;

        if ($config->getSessionExpireTime() && Params::session('user_id')
            && (((time() - Session::get(AppConstants::TIME_KEY)) >= $config->sessionExpireTime)))
        {
            $_SESSION = array();
            Application::saveRequest();

            $current->addNotice("Your session has timed-out, please log in again");

            Application::login($current);
        }
        elseif ($config->getSessionExpireTime()) {
            Session::set(AppConstants::TIME_KEY, time());
        }
    }

    /**
     * Loads a user from the session or request
     *
     * @return void
     */
    public static function loadUser()
    {
        global $config, $current;

        $policy = PolicyManager::getInstance();
        if ($user = $policy->restore()) {
            $current->user =& $user;
            Session::set(AppConstants::TIME_KEY, time());
        }

        if ((!$current->component->allows($current->action))                        /* current action denied */
            || (!$current->user && Params::request(AppConstants::FORCE_LOGIN_KEY))) /* request to log in (but not user) */
        {
            $config->info('User not found, loading Application::login');

            Application::login($current);
        }
    }

    /**
     * Restores a previously saved request
     *
     * @return void
     */
    public static function restoreRequest()
    {
        global $current, $config;

        if (!$current->user || !Session::get(AppConstants::SAVED_REQUEST_KEY)) {
            return;
        }

        if ($request = Application::popSavedRequest()) {
            if ($config->isDebugMode()) {
                $config->debug("restoring saved request: " . print_r($request, true));
            }

            if (($request->component)
                && ($obj = call_user_func(array($request->component, 'load'), $request->component)))
            {
                $_GET = $request->get;
                $_POST = $request->post;
                $_REQUEST = $request->request;

                $current->component = $obj;
                $current->action = $current->component->getAction($request->action);
                $current->stage = $request->stage;
            }
        }
    }

    /**
     * Set the language & theme
     *
     * @return void
     */
    public static function setLanguageAndTheme()
    {
        global $config, $current;

        /*
         * set language from A.) Cookies, B.) _lang_id request, C.) default
         */
        {
            $langId = (int) Params::cookie(AppConstants::LANGUAGE_COOKIE, 0);

            if (Params::request(AppConstants::LANGUAGE_KEY)) {
                $langId = (int) Params::request(AppConstants::LANGUAGE_KEY);
                setcookie(LANGUAGE_COOKIE, $langId, time() + EXPIRY_TIME);
            }

            if ($langId) {
                I18N::set($langId);
            }
            else {
                I18N::set('EN');
            }
        }

        $policy = PolicyManager::getInstance();
        $current->theme = $policy->getTheme();
        if (!$current->theme) {
            $message = 'A theme could not be resonably resolved';
            $config->error($message);
            throw new Exception($message);
        }
    }

    /**
     * Render the current setup to the user
     *
     * @return void
     */
    public static function render()
    {
        global $config, $current;

        if (Params::request(AppConstants::NO_HTML_KEY)) {
            Application::performAction($current->component, $current->action, $current->stage);

            $config->debug('output: ' . $current->component->getBuffer());

            $current->component->flush();
            return;
        }

        $layout = new LayoutDescription();

        /*
         * +====================+
         * |  VIEW (cacheable)  <-------------^------------<
         * +====================+             |            |
         *                                 NO |            |
         * +====================+     +==============+     |
         * |      VALIDATE      |-----> Return True? |     |
         * +====================+     +==============+     |
         *                                    |            |
         * +====================+             |            |
         * |       PERFORM      <-------------v YES        |
         * +=========|==========+                          |
         *           |                                     |
         * +=========v==========+                          |
         * |    Return False?   |--------------------------^ YES
         * +====================+
         */
        switch ($current->stage) {
            default:
            case Stage::VIEW:
                Application::performAction($current->component, $current->action, $current->stage);
                break;
            case Stage::VALIDATE:
                if (!Application::call($current->component, $current->action, Stage::VALIDATE)) {
                    Application::performAction($current->component, $current->action, Stage::VIEW);
                    break;
                }
            case Stage::PERFORM:
                if (!Application::call($current->component, $current->action, Stage::PERFORM)) {
                    Application::performAction($current->component, $current->action, Stage::VIEW);
                }

                break;
        }

        /*
         * populate the LayoutDescription
         */
        {
            $combinedItems = array_merge($layout->getTopNavigation(), $layout->getLeftNavigation(),
                                         $layout->getRightNavigation(), $layout->getBottomNavigation());

            $vars = get_object_vars($current->component);
            foreach ($vars as $prop => $val) {
                if (property_exists($layout, $prop)) {
                    $layout->$prop = $val;
                }
            }

            $layout->isPopup = Params::request(AppConstants::POPUP_KEY, false);
            $layout->component = $current->component;
            $layout->searchWord = Params::request(AppConstants::KEYWORD_KEY);
            $layout->isHomePage = ($current->component->getClass() == $config->getDefaultComponent())
                                  && ($current->action->id == $config->getDefaultAction());

            if ($layout->currentItem =& NavigatorItem::find($current->id, $combinedItems)) {
                $layout->currentItem->isCurrent = true;
                $layout->topLevelItem =& $layout->currentItem->getTopLevelParent();
            }

            /*
             * modules
             */
            {
                foreach (LifeCycleManager::onGetLeftModules() as $module) {
                    Application::performModule($module, Module::POSITION_LEFT);
                    $layout->addLeftModule($module);
                }

                foreach (LifeCycleManager::onGetTopModules() as $module) {
                    Application::performModule($module, Module::POSITION_TOP);
                    $layout->addTopModule($module);
                }

                foreach (LifeCycleManager::onGetRightModules() as $module) {
                    Application::performModule($module, Module::POSITION_RIGHT);
                    $layout->addRightModule($module);
                }

                foreach (LifeCycleManager::onGetBottomModules() as $module) {
                    Application::performModule($module, Module::POSITION_BOTTOM);
                    $layout->addBottomModule($module);
                }
            }
        }

        //print_r($layout);exit(0);
        $current->layout =& $layout;

        LifeCycleManager::onPreRender($current->layout);
        {
            $layout->content = $current->component->getBuffer();
            if (!$config->isDebugMode()) {
                $current->theme->addFilter(new WhitespaceFilter());
            }

            /*
             * set the current path to the theme location & display
             */
            Application::setPath($current->theme->getPath());
            $current->theme->onDisplay($layout);
            $current->theme->flush();
        }
        LifeCycleManager::onPostRender();
    }
}

?>