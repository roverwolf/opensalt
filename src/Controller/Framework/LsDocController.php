<?php

namespace App\Controller\Framework;

use App\Command\CommandDispatcherTrait;
use App\Command\Framework\AddDocumentCommand;
use App\Command\Framework\DeleteDocumentCommand;
use App\Command\Framework\DeriveDocumentCommand;
use App\Command\Framework\LockDocumentCommand;
use App\Command\Framework\UpdateDocumentCommand;
use App\Command\Framework\UpdateFrameworkCommand;
use App\Exception\AlreadyLockedException;
use App\Form\Type\RemoteCaseServerType;
use App\Form\Type\LsDocCreateType;
use App\Entity\User\User;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use App\Entity\Framework\LsDoc;
use App\Form\Type\LsDocType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * LsDoc controller.
 *
 * @Route("/cfdoc")
 */
class LsDocController extends AbstractController
{
    use CommandDispatcherTrait;

    /**
     * @var ClientInterface
     */
    private $guzzleJsonClient;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authChecker;

    public function __construct(ClientInterface $guzzleJsonClient, AuthorizationCheckerInterface $authChecker)
    {
        $this->guzzleJsonClient = $guzzleJsonClient;
        $this->authChecker = $authChecker;
    }

    /**
     * Lists all LsDoc entities.
     *
     * @Route("/", methods={"GET"}, name="lsdoc_index")
     * @Template()
     *
     * @return array
     */
    public function indexAction(?UserInterface $user = null)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var LsDoc[] $results */
        $results = $em->getRepository(LsDoc::class)->findBy(
            [],
            ['creator' => 'ASC', 'title' => 'ASC', 'adoptionStatus' => 'ASC']
        );

        $lsDocs = [];
        $loggedIn = $user instanceof User;
        foreach ($results as $lsDoc) {
            // Optimization: All but "Private Draft" are viewable to everyone, only auth check "Private Draft"
            if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $lsDoc->getAdoptionStatus() || ($loggedIn && $this->authChecker->isGranted('view', $lsDoc))) {
                $lsDocs[] = $lsDoc;
            }
        }

        return [
            'lsDocs' => $lsDocs,
        ];
    }

    /**
     * Show frameworks from a remote system
     *
     * @Route("/remote", methods={"GET", "POST"}, name="lsdoc_remote_index")
     * @Template()
     *
     * @return array
     */
    public function remoteIndexAction(Request $request)
    {
        $form = $this->createForm(RemoteCaseServerType::class);
        $form->handleRequest($request);

        $docs = null;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $docs = $this->loadDocumentListFromHost($form->getData()['hostname']);
            } catch (\Exception $e) {
                $form->get('hostname')->addError(new FormError($e->getMessage()));
            }
        }

        return [
            'form' => $form->createView(),
            'docs' => $docs,
        ];
    }

    /**
     * @param string $urlPrefix
     *
     * @return \Psr\Http\Message\ResponseInterface
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function loadDocumentsFromServer(string $urlPrefix)
    {
        $list = $this->guzzleJsonClient->request(
            'GET',
            $urlPrefix.'/ims/case/v1p0/CFDocuments',
            [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        return $list;
    }

    /**
     * Creates a new LsDoc entity.
     *
     * @Route("/new", methods={"GET", "POST"}, name="lsdoc_new")
     * @Template()
     * @Security("is_granted('create', 'lsdoc')")
     *
     * @param Request $request
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function newAction(Request $request)
    {
        $lsDoc = new LsDoc();
        $form = $this->createForm(LsDocCreateType::class, $lsDoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new AddDocumentCommand($lsDoc);
                $this->sendCommand($command);

                return $this->redirectToRoute(
                    'doc_tree_view',
                    array('slug' => $lsDoc->getSlug())
                );
            } catch (\Exception $e) {
                $form->addError(new FormError('Error adding new document: '.$e->getMessage()));
            }
        }

        return [
            'lsDoc' => $lsDoc,
            'form' => $form->createView(),
        ];
    }

    /**
     * Finds and displays a LsDoc entity.
     *
     * @Route("/{id}.{_format}", methods={"GET"}, defaults={"_format"="html"}, name="lsdoc_show")
     * @Template()
     * @Security("is_granted('view', lsDoc)")
     *
     * @param LsDoc $lsDoc
     * @param string $_format
     *
     * @return array
     */
    public function showAction(LsDoc $lsDoc, $_format = 'html')
    {
        if ('json' === $_format) {
            // Redirect?  Change Action for Template?
            return ['lsDoc' => $lsDoc];
        }

        $deleteForm = $this->createDeleteForm($lsDoc);

        return [
            'lsDoc' => $lsDoc,
            'delete_form' => $deleteForm->createView(),
        ];
    }

    /**
     * Update a framework given a CSV or external File.
     *
     * @Route("/doc/{id}/update", methods={"POST"}, name="lsdoc_update")
     * @Security("is_granted('edit', lsDoc)")
     */
    public function updateAction(Request $request, LsDoc $lsDoc)
    {
        $response = new JsonResponse();
        $fileContent = $request->request->get('content');
        $cfItemKeys = $request->request->get('cfItemKeys');
        $frameworkToAssociate = $request->request->get('frameworkToAssociate');

        $command = new UpdateFrameworkCommand($lsDoc, base64_decode($fileContent), $frameworkToAssociate, $cfItemKeys);
        $this->sendCommand($command);

        return $response->setData([
            'message' => 'Success',
        ]);
    }

    /**
     * Update a framework given a CSV or external File on a derivative framework.
     *
     * @Route("/doc/{id}/derive", methods={"POST"}, name="lsdoc_update_derive")
     * @Security("is_granted('create', 'lsdoc')")
     *
     * @param Request $request
     * @param LsDoc $lsDoc
     *
     * @return Response
     */
    public function deriveAction(Request $request, LsDoc $lsDoc): Response
    {
        $fileContent = $request->request->get('content');
        $frameworkToAssociate = $request->request->get('frameworkToAssociate');

        $command = new DeriveDocumentCommand($lsDoc, base64_decode($fileContent), $frameworkToAssociate);
        $this->sendCommand($command);
        $derivedDoc = $command->getDerivedDoc();

        return new JsonResponse([
            'message' => 'Success',
            'new_doc_id' => $derivedDoc->getId()
        ]);
    }

    /**
     * Displays a form to edit an existing LsDoc entity.
     *
     * @Route("/{id}/edit", methods={"GET", "POST"}, name="lsdoc_edit")
     * @Template()
     * @Security("is_granted('edit', lsDoc)")
     *
     * @param Request $request
     * @param LsDoc $lsDoc
     * @param User $user
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction(Request $request, LsDoc $lsDoc, UserInterface $user)
    {
        $ajax = $request->isXmlHttpRequest();

        try {
            $command = new LockDocumentCommand($lsDoc, $user);
            $this->sendCommand($command);
        } catch (AlreadyLockedException $e) {
            return $this->render(
                'framework/ls_doc/locked.html.twig',
                []
            );
        }

        $deleteForm = $this->createDeleteForm($lsDoc);
        $editForm = $this->createForm(
            LsDocType::class,
            $lsDoc,
            ['ajax' => $ajax]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $command = new UpdateDocumentCommand($lsDoc);
                $this->sendCommand($command);

                if ($ajax) {
                    return new Response('OK', Response::HTTP_ACCEPTED);
                }

                return $this->redirectToRoute(
                    'lsdoc_edit',
                    array('id' => $lsDoc->getId())
                );
            } catch (\Exception $e) {
                $editForm->addError(new FormError('Error upating new document: '.$e->getMessage()));
            }
        }

        $ret = [
            'lsDoc' => $lsDoc,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ];

        if ($ajax && $editForm->isSubmitted() && !$editForm->isValid()) {
            return $this->render(
                'framework/ls_doc/edit.html.twig',
                $ret,
                new Response('', Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $ret;
    }

    /**
     * Deletes a LsDoc entity.
     *
     * @Route("/{id}", methods={"DELETE"}, name="lsdoc_delete")
     * @Security("is_granted('delete', lsDoc)")
     *
     * @param Request $request
     * @param LsDoc $lsDoc
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, LsDoc $lsDoc): Response
    {
        if ($request->isXmlHttpRequest()) {
            $token = $request->request->get('token');
            if ($this->isCsrfTokenValid('DELETE '.$lsDoc->getId(), $token)) {
                try {
                    $this->deleteFramework($lsDoc);

                    return new JsonResponse('OK');
                } catch (\Exception $e) {
                    return new JsonResponse(['error' => ['message' => 'Error deleting framework']], Response::HTTP_BAD_REQUEST);
                }
            }

            return new JsonResponse(['error' => ['message' => 'CSRF token invalid']], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createDeleteForm($lsDoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deleteFramework($lsDoc);
        }

        return $this->redirectToRoute('lsdoc_index');
    }

    /**
     * Finds and displays a LsDoc entity.
     *
     * @Route("/{id}/export.{_format}", methods={"GET"}, requirements={"_format"="(json|html|null)"}, defaults={"_format"="json"}, name="lsdoc_export")
     * @Template()
     * @Security("is_granted('view', lsDoc)")
     *
     * @param LsDoc $lsDoc
     * @param string $_format
     *
     * @return array
     */
    public function exportAction(LsDoc $lsDoc, $_format = 'json')
    {
        $items = $this->getDoctrine()
            ->getRepository(LsDoc::class)
            ->findAllChildrenArray($lsDoc);

        return [
            'lsDoc' => $lsDoc,
            'items' => $items,
        ];
    }

    /**
     * Load the document list from a remote host
     *
     * @param string $hostname
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function loadDocumentListFromHost(string $hostname): array
    {
        // Remove any scheme or path from the passed value
        $hostname = preg_replace('#^(?:https?://)?([^/]+)(?:/.*)#', '$1', $hostname);

        try {
            $remoteResponse = $this->loadDocumentsFromServer(
                'https://'.$hostname
            );
        } catch (\Exception $e) {
            try {
                $remoteResponse = $this->loadDocumentsFromServer(
                    'http://'.$hostname
                );
            } catch (\Exception $e) {
                throw new \Exception("Could not access CASE API on {$hostname}.");
            }
        }

        try {
            $docJson = $remoteResponse->getBody()->getContents();
            $docs = json_decode($docJson, true);
            $docs = $docs['CFDocuments'];
            foreach ($docs as $key => $doc) {
                if (empty($doc['creator'])) {
                    $docs[$key]['creator'] = 'Unknown';
                }
                if (empty($doc['title'])) {
                    $docs[$key]['title'] = 'Unknown';
                }
            }
            usort(
                $docs,
                function ($a, $b) {
                    if ($a['creator'] !== $b['creator']) {
                        return $a['creator'] <=> $b['creator'];
                    }

                    return $a['title'] <=> $b['title'];
                }
            );
        } catch (\Exception $e) {
            $docs = null;
        }

        return $docs;
    }

    /**
     * @param LsDoc $lsDoc
     */
    protected function deleteFramework(LsDoc $lsDoc): void
    {
        $command = new DeleteDocumentCommand($lsDoc);
        $this->sendCommand($command);
    }

    /**
     * Creates a form to delete a LsDoc entity.
     *
     * @param LsDoc $lsDoc The LsDoc entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(LsDoc $lsDoc)
    {
        return $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'lsdoc_delete',
                    array('id' => $lsDoc->getId())
                )
            )
            ->setMethod('DELETE')
            ->getForm();
    }
}
