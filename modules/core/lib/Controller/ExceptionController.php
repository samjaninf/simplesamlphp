<?php

namespace SimpleSAML\Module\core\Controller;

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the core module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\core
 */
class ExceptionController
{
    /** @var \SimpleSAML\Configuration */
    protected $config;

    /** @var \SimpleSAML\Session */
    protected $session;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and auth source configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration              $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session                    $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }


    /**
     * Show cardinality error.
     *
     * @param Request $request The request that lead to this login operation.
     * @throws \SimpleSAML\Error\BadRequest
     * @return \SimpleSAML\XHTML\Template|\Symfony\Component\HttpFoundation\RedirectResponse
     *   An HTML template or a redirection if we are not authenticated.
     */
    public function cardinality(Request $request)
    {
        $stateId = $request->get('StateId', false);
        if ($stateId === false) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }

        /** @var array $state */
        $state = Auth\State::loadState($stateId, 'core:cardinality');

        Logger::stats(
            'core:cardinality:error '.$state['Destination']['entityid'].' '.$state['saml:sp:IdP'].
            ' '.implode(',', array_keys($state['core:cardinality:errorAttributes']))
        );

        $t = new Template($this->config, 'core:cardinality_error.tpl.php');
        $t->data['cardinalityErrorAttributes'] = $state['core:cardinality:errorAttributes'];
        if (isset($state['Source']['auth'])) {
            $t->data['LogoutURL'] = Module::getModuleURL(
                'core/authenticate.php',
                ['as' => $state['Source']['auth']]
            )."&logout";
        }
        header('HTTP/1.0 403 Forbidden');
        $t->show();
    }


    /**
     * Show missing cookie error.
     *
     * @param Request $request The request that lead to this login operation.
     * @return \SimpleSAML\XHTML\Template|\Symfony\Component\HttpFoundation\RedirectResponse
     *   An HTML template or a redirection if we are not authenticated.
     */
    public function nocookie(Request $request)
    {
        $retryURL = $request->get('retryURL', null);
        if ($retryURL !== null) {
            $retryURL = Utils\HTTP::checkURLAllowed(strval($retryURL));
        }

        $t = new Template($this->config, 'core:no_cookie.tpl.php');
        $translator = $t->getTranslator();

        /** @var string $header */
        $header = $translator->t('{core:no_cookie:header}');

        /** @var string $desc */
        $desc = $translator->t('{core:no_cookie:description}');

        /** @var string $retry */
        $retry = $translator->t('{core:no_cookie:retry}');

        $t->data['header'] = htmlspecialchars($header);
        $t->data['description'] = htmlspecialchars($desc);
        $t->data['retry'] = htmlspecialchars($retry);
        $t->data['retryURL'] = $retryURL;
        $t->show();
    }


    /**
     * Show a warning to an user about the SP requesting SSO a short time after
     * doing it previously.
     *
     * @param Request $request The request that lead to this login operation.
     *
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     * An HTML template, a redirect or a "runnable" response.
     *
     * @throws \SimpleSAML\Error\BadRequest
     */
    public function shortSsoInterval(Request $request)
    {
        $stateId = $request->get('StateId', false);
        if ($stateId === false) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }

        /** @var array $state */
        $state = Auth\State::loadState($stateId, 'core:short_sso_interval');

        $continue = $request->get('continue', false);
        if ($continue !== false) {
            // The user has pressed the continue/retry-button
            Auth\ProcessingChain::resumeProcessing($state);
        }

        $t = new Template($this->config, 'core:short_sso_interval.tpl.php');
        $translator = $t->getTranslator();
        $t->data['target'] = Module::getModuleURL('core/short_sso_interval.php');
        $t->data['params'] = ['StateId' => $stateId];
        $t->data['trackId'] = $this->session->getTrackID();
        $t->data['header'] = $translator->t('{core:short_sso_interval:warning_header}');
        $t->data['autofocus'] = 'contbutton';
        $t->show();
    }
}
