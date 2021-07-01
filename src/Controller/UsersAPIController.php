<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Mappers\UserMapper;
use App\Mappers\SpecialityMapper;
use App\Models\UserModel;
use App\Helpers\PDOConditionMapper;
use App\Interfaces\ConditionInterface;

final class UsersAPIController extends BaseController
{
    public function fetchUserProfile(Request $request, Response $response, array $args = []): Response
	{
        $users_id = $args['users_id'];

        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(" `users`.`id` = ? AND `users`.`removed` = ? AND `users`.`deactivated` = ? ",[ $users_id, 0, 0 ]);

        $mapper = new UserMapper($this->database_adapter);
        $results = $mapper->fetch($condition_mapper);

        $data['id'] = (int) $results['id'];
        $data['first_name'] = $results['first_name'];
        $data['last_name'] = $results['last_name'];
        $data['image_url'] = $results['image_url'];
        $data['email'] = $results['email'];
        $data['country'] = $results['country'];

        $speciality_mapper = new SpecialityMapper($this->database_adapter);
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(" `id` = ? ", [ $results['speciality_id'] ]);
        $speciality_result = $speciality_mapper->fetch($condition_mapper);
        $data['speciality_id'] = (int) $speciality_result['id'];
        $data['speciality'] = $speciality_result['title'];

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

	public function fetchSmokingPref(Request $request, Response $response, array $args = []): Response
	{
        $users_id = $args['users_id'];

        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(" `users`.`id` = ? AND `users`.`removed` = ? AND `users`.`deactivated` = ? ",[ $users_id, 0, 0 ]);

        $mapper = new UserMapper($this->database_adapter);
        $results = $mapper->fetch($condition_mapper);
        $data['id'] = $results['id'];
        $data['smoking_pref'] = $results['smoking_pref'];

        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function uploadImage (Request $request, Response $response, $args) {

        $parsed_body = is_null($request->getParsedBody()) ? json_decode($request->getBody()->getContents(), true) : $request->getParsedBody();
        $users_id = (int) $args['users_id'];

        $condition = new PDOConditionMapper();
        $condition->where(' `users`.`id`= ? AND `users`.`removed` = ?', [$users_id, 0]);
        // Prepare user mapper
        $mapper = new UserMapper($this->database_adapter);
        $user = $mapper->fetch($condition);
        // Create the model
        $user_model = new UserModel($user);
        // Upload image to s3 and assign url
        $image_url = $this->s3_helper->move($parsed_body['image_url'], "users_images");
        $user_model->setImageUrl($image_url[0]);

        // Update user model and save in db
        $condition_mapper = new PDOConditionMapper();
        $condition_mapper->where(' `id` = ?', [$users_id]);
        $condition_mapper->update("`image_url` = ?", [$user_model->getImageUrl()]);
        $mapper->update($condition_mapper);

        return $response->withStatus(200);
    }

    public function searchFriend(Request $request, Response $response, array $args = []): Response
	{
        $users_id = (int) $args['users_id'];
        $query_params = $request->getQueryParams();
        $first_name = isset($query_params['first_name']) ? $query_params['first_name'] : "";
        $last_name = isset($query_params['last_name']) ? $query_params['last_name'] : "";
        $code = isset($query_params['code']) ? $query_params['code'] : "";
        $condition_mapper = $request->getAttribute('QUERY_PAGINATION');

        $base_param = [0,0,"normal"];
        $base_sql = " `users`.`removed` = ? AND `users`.`deactivated` = ? AND `type`.`name` = ?";

        // Or by search field
        if (isset($query_params['search'])) {
            $search = $query_params['search'];
            $base_sql .=" AND (`users`.`first_name` LIKE ? OR `users`.`last_name` LIKE ? OR `users`.`code` = ?)";
            $base_param[] = "%$search%";
            $base_param[] = "%$search%";
            $base_param[] = "$search";
        }

        $condition_mapper->where($base_sql, $base_param);
        $mapper = new UserMapper($this->database_adapter);
        $results = $mapper->fetchAllPaginated($condition_mapper);

        $data = [];
        foreach ($results as $key => $row) {
            $obj['id'] = $row['id'];
            $obj['first_name'] = $row['first_name'];
            $obj['last_name'] = $row['last_name'];
            $obj['image_url'] = $row['image_url'];
            $obj['generated_id'] = $row['generated_id'];
            $obj['dob'] = $row['dob'];
            $obj['email'] = $row['email'];
            $obj['phone_number'] = $row['phone_number'];
            $obj['smoking_pref'] = $row['smoking_pref'];
            $obj['referral_code'] = $row['referral_code'];
            $data[] = $obj;
        }

        $url_route = $this->router->urlFor('search_friend',['users_id'=>$users_id]);
        $count = $mapper->fetchCount($condition_mapper); // Get the count for the pagination
        $results = \App\Helpers\PaginationHelper::WrapPrevNextPages($url_route ,$request,$data,$count);
        
        $payload = json_encode($results, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
