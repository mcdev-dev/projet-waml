<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MessageController
 * @package App\Controller
 * @Route("/message")
 */
class MessageController extends AbstractController
{
    /**
     * @Route("/")
     */
    public function index()
    {

        /*************************************************************
         * RECHERCHE MESSAGES RECUS ET ENVOYES SANS DOUBLONS DE USER *
         *************************************************************/
        if (empty($this->getUser())) {
            return $this->redirectToRoute('app_registration_login');
        } else {
            $sentMessages = $this->getDoctrine()->getManager()->getRepository(Message::class)->findLastMessagesSent($this->getUser());



            $receivedMessages = $this->getDoctrine()->getManager()->getRepository(Message::class)
                ->createQueryBuilder('r')
                ->where('(r.receivingUser = :id1)')
                ->setParameter(':id1', $this->getUser())
                //->groupby('r.sendingUser')
                ->orderBy('r.sendingDate', 'DESC')
                ->getQuery()->getResult();

            return $this->render('message/index.html.twig', [
                'sentMessages' => $sentMessages,
                'receivedMessages' => $receivedMessages
            ]);
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/convers/{id}/{idPost}", defaults={"idPost": null})
     */
    public function send(
        Request $request,
        EntityManagerInterface $manager,
        UserRepository $userRepository,
        PostRepository $postRepository,
        \Swift_Mailer $mailer,
        $id,
        $idPost)
    {

        /*********************************
         * CREATION D'UN NOUVEAU MESSAGE *
         *********************************/
        if (empty($this->getUser())) {
            return $this->redirectToRoute('app_registration_login');
        } else {

            $post = null;
            if (!is_null($idPost)) {
                $post = $postRepository->find($idPost);
            }

            $newMessage = new Message();
            $newMessage
                ->setSendingUser($this->getUser())
                ->setReceivingUser($userRepository->find($id));

            if (!is_null($post)) {
                $newMessage->setPost($post);
            }

            $messageForm = $this->createForm(MessageType::class, $newMessage);
            $messageForm->handleRequest($request);

            if ($messageForm->isSubmitted()) {
                if ($messageForm->isValid()) {


                    $manager->persist($newMessage);
                    $manager->flush();

                    $this->addFlash('success', 'Votre message est envoyé');

                    $notification = (new \Swift_Message('Nouveau message dans votre WALM inbox'))
                        ->setFrom(array('ollivier.johan92@gmail.com' => 'Projet-defoulement'))
                        ->setTo($newMessage->getReceivingUser()->getEmail())
                        ->setBody(
                            $this->renderView(
                                'message/emails/newMessage.html.twig',
                                [
                                    'firstname' => $newMessage->getReceivingUser()->getFirstname()
                                ]
                            ),
                            'text/html'
                        );
                    $mailer->send($notification);

                    return $this->redirectToRoute('app_message_send', ['id' => $id]);
                }
            }

            $contact = $newMessage->getReceivingUser()->getFirstname();

            $messages = $this->getDoctrine()->getManager()->getRepository(Message::class)
                ->createQueryBuilder('m')
                ->where('(m.receivingUser = :id1 AND m.sendingUser = :id2) OR (m.sendingUser = :id1 AND m.receivingUser = :id2)')
                ->setParameter(':id1', $id)
                ->setParameter(':id2', $this->getUser())
                ->orderBy('m.sendingDate', 'DESC')
                ->getQuery()->getResult();


            return $this->render(
                'message/conversation.html.twig',
                [
                    'messages' => $messages,
                    'message' => $newMessage,
                    'message_form' => $messageForm->createView(),
                    'contact' => $contact,
                    'post' => $post
                ]
            );
        }
    }


    public function newMessage(User $user, \Swift_Mailer $mailer)
    {

        if ($user->getReceivedMessages())

            $notification = (new \Swift_Message('Nouveau message dans votre WALM inbox'))
                ->setFrom(array('ollivier.johan92@gmail.com' => 'Projet-defoulement'))
                ->setTo($user->getEmail())
                ->setBody(
                    $this->renderView(
                        'message/emails/newMessage.html.twig',
                        ['name' => $user]
                    ),
                    'text/html'
                );


        return $mailer->send($notification);

        //return $this->render(...);
    }


}
