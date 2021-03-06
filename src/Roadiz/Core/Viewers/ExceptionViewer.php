<?php
/**
 * Copyright (c) 2016. Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file ExceptionViewer.php
 * @author Ambroise Maupate <ambroise@rezo-zero.com>
 */

namespace RZ\Roadiz\Core\Viewers;

use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use RZ\Roadiz\Core\Exceptions\MaintenanceModeException;
use RZ\Roadiz\Core\Exceptions\NoConfigurationFoundException;
use RZ\Roadiz\Core\Exceptions\PreviewNotAllowedException;
use RZ\Roadiz\CMS\Controllers\CmsController;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class ExceptionViewer
 * @package RZ\Roadiz\Core\Viewers
 */
class ExceptionViewer
{
    private $foreground_colors = [];
    private $background_colors = [];

    /**
     * ExceptionViewer constructor.
     */
    public function __construct()
    {
        // Set up shell colors
        $this->foreground_colors['black'] = '0;30';
        $this->foreground_colors['dark_gray'] = '1;30';
        $this->foreground_colors['blue'] = '0;34';
        $this->foreground_colors['light_blue'] = '1;34';
        $this->foreground_colors['green'] = '0;32';
        $this->foreground_colors['light_green'] = '1;32';
        $this->foreground_colors['cyan'] = '0;36';
        $this->foreground_colors['light_cyan'] = '1;36';
        $this->foreground_colors['red'] = '0;31';
        $this->foreground_colors['light_red'] = '1;31';
        $this->foreground_colors['purple'] = '0;35';
        $this->foreground_colors['light_purple'] = '1;35';
        $this->foreground_colors['brown'] = '0;33';
        $this->foreground_colors['yellow'] = '1;33';
        $this->foreground_colors['light_gray'] = '0;37';
        $this->foreground_colors['white'] = '1;37';

        $this->background_colors['black'] = '40';
        $this->background_colors['red'] = '41';
        $this->background_colors['green'] = '42';
        $this->background_colors['yellow'] = '43';
        $this->background_colors['blue'] = '44';
        $this->background_colors['magenta'] = '45';
        $this->background_colors['cyan'] = '46';
        $this->background_colors['light_gray'] = '47';
    }

    /**
     * @param \Exception $exception
     * @return int
     */
    public function getHttpStatusCode(\Exception $exception)
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        } elseif ($exception instanceof ResourceNotFoundException) {
            return Response::HTTP_NOT_FOUND;
        } elseif ($exception instanceof MaintenanceModeException) {
            return Response::HTTP_SERVICE_UNAVAILABLE;
        } elseif ($exception instanceof AccessDeniedException ||
            $exception instanceof AccessDeniedHttpException ||
            $exception instanceof PreviewNotAllowedException) {
            return Response::HTTP_FORBIDDEN;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
 * @param \Exception $e
 * @return string
 */
    public function getHumanExceptionTitle(\Exception $e)
    {
        if ($e instanceof InvalidConfigurationException) {
            return "Roadiz configuration is not valid.";
        }

        if ($e instanceof NoConfigurationFoundException) {
            return "No configuration file has been found. Did you run composer install before using Roadiz?";
        }

        if ($e instanceof ResourceNotFoundException ||
            $e instanceof NotFoundHttpException) {
            return "Resource not found.";
        }

        if ($e instanceof ConnectionException ||
            $e instanceof \Doctrine\DBAL\ConnectionException) {
            return "Your database is not reachable. Did you run install before using Roadiz?";
        }

        if ($e instanceof TableNotFoundException) {
            return "Your database is not synchronised to Roadiz data schema. Did you run install before using Roadiz?";
        }

        if ($e instanceof AccessDeniedException ||
            $e instanceof AccessDeniedHttpException ||
            $e instanceof PreviewNotAllowedException) {
            return "Oups! Wrong way, you are not supposed to be here.";
        }

        return "A problem occurred on our website. We are working on this to be back soon.";
    }

    /**
     * @param \Exception $e
     * @return string
     */
    public function getJsonError(\Exception $e)
    {
        if ($e instanceof InvalidConfigurationException) {
            return "invalid_configuration";
        }

        if ($e instanceof NoConfigurationFoundException) {
            return "no_configuration_file";
        }

        if ($e instanceof ResourceNotFoundException ||
            $e instanceof NotFoundHttpException) {
            return "not_found";
        }

        if ($e instanceof ConnectionException ||
            $e instanceof \Doctrine\DBAL\ConnectionException) {
            return "database_not_reachable";
        }

        if ($e instanceof TableNotFoundException) {
            return "database_not_uptodate";
        }

        if ($e instanceof AccessDeniedException ||
            $e instanceof AccessDeniedHttpException ||
            $e instanceof PreviewNotAllowedException) {
            return "access_denied";
        }

        return "general_error";
    }


    /**
     * @param \Exception $e
     * @param Request $request
     * @param bool $debug
     * @return JsonResponse|Response
     */
    public function getResponse(\Exception $e, Request $request, $debug = false)
    {
        /*
         * Log error before displaying a fallback page.
         */
        $class = get_class($e);

        $humanMessage = $this->getHumanExceptionTitle($e);

        if (php_sapi_name() === 'cli') {
            return new Response(
                implode(PHP_EOL, [
                    $this->getColoredString('['.$class.']', 'white', 'red'),
                    $this->getColoredString($e->getMessage(), 'red', null),
                ]).PHP_EOL,
                $this->getHttpStatusCode($e),
                [
                    'content-type' => 'text/plain',
                ]
            );
        } elseif ($this->isFormatJson($request)) {
            $data = [
                'error' => $this->getJsonError($e),
                'error_message' => $e->getMessage(),
                'message' => $e->getMessage(),
                'exception' => $class,
                'humanMessage' => $humanMessage,
                'status' => 'danger',
            ];
            if ($debug) {
                $data['error_trace'] =  $e->getTrace();
            }
            return new JsonResponse($data, $this->getHttpStatusCode($e));
        } else {
            $html = file_get_contents(CmsController::getViewsFolder() . '/emerg.html');
            $html = str_replace('{{ http_code }}', $this->getHttpStatusCode($e), $html);
            $html = str_replace('{{ human_message }}', $humanMessage, $html);

            if ($this->getHttpStatusCode($e) === Response::HTTP_FORBIDDEN) {
                $html = str_replace('{{ smiley }}', '🤔', $html);
            } elseif ($this->getHttpStatusCode($e) === Response::HTTP_NOT_FOUND) {
                $html = str_replace('{{ smiley }}', '🧐', $html);
            } {
                $html = str_replace('{{ smiley }}', '🤕', $html);
            }

            if ($debug) {
                $html = str_replace('{{ message }}', $e->getMessage(), $html);
                $trace = preg_replace('#([^\n]+)#', '<p>$1</p>', $e->getTraceAsString());
                $html = str_replace('{{ details }}', $trace, $html);
                $html = str_replace('{{ exception }}', $class, $html);
            } else {
                $html = str_replace('{{ message }}', '', $html);
                $html = str_replace('{{ details }}', '', $html);
                $html = str_replace('{{ exception }}', '', $html);
            }

            return new Response(
                $html,
                $this->getHttpStatusCode($e),
                [
                    'content-type' => 'text/html',
                    'X-Error-Reason' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function isFormatJson(Request $request)
    {
        if ($request->attributes->has('_format') &&
            $request->attributes->get('_format') == 'json') {
            return true;
        }

        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            return true;
        }

        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return true;
        }

        return false;
    }

    /**
     * @param $string
     * @param null $foreground_color
     * @param null $background_color
     * @return string
     */
    public function getColoredString($string, $foreground_color = null, $background_color = null)
    {
        $colored_string = "";

        // Check if given foreground color found
        if (isset($this->foreground_colors[$foreground_color])) {
            $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
        }
        // Check if given background color found
        if (isset($this->background_colors[$background_color])) {
            $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
        }

        // Add string and end coloring
        $colored_string .=  $string . "\033[0m";

        return $colored_string;
    }

    /**
     * @return array Returns all foreground color names
     */
    public function getForegroundColors()
    {
        return array_keys($this->foreground_colors);
    }

    /**
     * @return array Returns all background color names
     */
    public function getBackgroundColors()
    {
        return array_keys($this->background_colors);
    }
}
