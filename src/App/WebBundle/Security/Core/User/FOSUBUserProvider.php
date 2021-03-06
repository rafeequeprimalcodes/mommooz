<?php

namespace App\WebBundle\Security\Core\User;
 
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\Security\Core\User\UserInterface;
use App\FrontBundle\Entity\Image;

class FOSUBUserProvider extends BaseClass
{
    /**
     * {@inheritDoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $property = $this->getProperty($response);
        $username = $response->getUsername();
        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();
        $setter = 'set'.ucfirst($service);
        $setter_id = $setter.'Id';
        $setter_token = $setter.'AccessToken';
        //we "disconnect" previously connected users
        if (null !== $previousUser = $this->userManager->findUserBy(array($property => $username))) {
            $previousUser->$setter_id(null);
            $previousUser->$setter_token(null);
            $this->userManager->updateUser($previousUser);
        }
        //we connect current user
        $user->$setter_id($username);
        $user->$setter_token($response->getAccessToken());
        $this->userManager->updateUser($user);
    }
    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $username = $response->getUsername();
        $data = $response->getResponse();
        $email = trim($response->getEmail());
        $user = $this->userManager->findUserBy(array('email' => $email));
        //when the user is registrating
        if (null === $user) {
            $service = $response->getResourceOwner()->getName();
            $setter = 'set'.ucfirst($service);
            $setter_id = $setter.'Id';
            $setter_token = $setter.'AccessToken';
            // create new user here
            $user = $this->userManager->createUser();
            $user->$setter_id($username);
            $user->$setter_token($response->getAccessToken());
            //I have set all requested data with the user's username
            //modify here with relevant data
            $user->setFirstname($response->getFirstName());
            $user->setLastname($response->getLastName());
            $user->setEmail($email);
            $user->setUsername($response->getEmail());            
            $user->setPassword('momooz_cons');
            if($response->getEmail()){
                $user->setEnabled(true);
            } else {
                $user->setEnabled(false);
            }
            
            $user->setStatus(true);
            $user->regenerateSalt();
            $user->setCreatedOn(new \DateTime('now'));
            
            if($response->getProfilePicture()){
                $image = file_get_contents($response->getProfilePicture());
                if ($image !== false){
                    $img = 'data:image/jpg;base64,'.base64_encode($image);
                    $image = new Image();
                    $image->setImage($img);
                    $user->addImage($image);
                }
            }

            $this->userManager->updateUser($user);
            return $user;
        }
        
        if(!$user->getStatus()) {
            return null;
        }
        
        //if user exists - go with the HWIOAuth way
        $user = parent::loadUserByOAuthUserResponse($response);
        $serviceName = $response->getResourceOwner()->getName();
        $setter = 'set' . ucfirst($serviceName) . 'AccessToken';
        //update access token
        $user->$setter($response->getAccessToken());
        return $user;
    }
}