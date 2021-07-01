<?php

namespace App\Middleware;

use App\Helpers\PDOConditionMapper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class QueryMiddleware implements Middleware
{
    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    // public function __invoke($request, $response, $next)
    // {
    //     // $response->getBody()->write('BEFORE');

    //     $query_params = $request->getParams();
    //     $page = isset($query_params['page']) ? (int) $query_params['page'] : 1;
    //     $limit = isset($query_params['limit']) ? (int) $query_params['limit'] : 20;
    //     $offset = isset($query_params['offset']) ? (int) $query_params['offset'] : $limit;

    //     $condition_mapper = new PDOConditionMapper();
    //     $condition_mapper->setOffset($offset);
    //     $condition_mapper->setLimit($limit);
    //     $condition_mapper->setPage($page);

    //     $request = $request->withAttribute('QUERY_PAGINATION', $condition_mapper );
    //     $request = $request->withAttribute('PAGINATION_PAGE', $page );
    //     $request = $request->withAttribute('PAGINATION_OFFSET', $offset );
    //     $request = $request->withAttribute('PAGINATION_LIMIT', $limit );

    //     $response = $next($request, $response);
    //     // $response->getBody()->write('AFTER');    

    //     return $response;   
    // }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        $existingContent = (string) $response->getBody();

        $query_params = $request->getParams();
        $page = isset($query_params['page']) ? (int) $query_params['page'] : 1;
        $limit = isset($query_params['limit']) ? (int) $query_params['limit'] : 20;
        $offset = isset($query_params['offset']) ? (int) $query_params['offset'] : $limit;

        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->setOffset($offset);
        $condition_mapper->setLimit($limit);
        $condition_mapper->setPage($page);

        $request = $request->withAttribute('QUERY_PAGINATION', $condition_mapper );
        $request = $request->withAttribute('PAGINATION_PAGE', $page );
        $request = $request->withAttribute('PAGINATION_OFFSET', $offset );
        $request = $request->withAttribute('PAGINATION_LIMIT', $limit );
    
        return $response;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $query_params = $request->getQueryParams();
        $page = isset($query_params['page']) ? (int) $query_params['page'] : 1;
        $limit = isset($query_params['limit']) ? (int) $query_params['limit'] : 20;
        $offset = isset($query_params['offset']) ? (int) $query_params['offset'] : $limit;

        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->setOffset($offset);
        $condition_mapper->setLimit($limit);
        $condition_mapper->setPage($page);

        $request = $request->withAttribute('QUERY_PAGINATION', $condition_mapper );
        $request = $request->withAttribute('PAGINATION_PAGE', $page );
        $request = $request->withAttribute('PAGINATION_OFFSET', $offset );
        $request = $request->withAttribute('PAGINATION_LIMIT', $limit );

        return $handler->handle($request);
    }

}