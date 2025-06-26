<?php

declare(strict_types=1);

namespace  MauticPlugin\MauticContactMergeApiBundle\Controller\Api;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\ApiBundle\Helper\EntityResultHelper;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Form\Type\BooleanType;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Deduplicate\ContactMerger;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\Count;

class ContactMergeApiController extends CommonApiController {

    public function __construct(
        CorePermissions $security,
        Translator $translator,
        EntityResultHelper $entityResultHelper,
        RouterInterface $router,
        FormFactoryInterface $formFactory,
        AppVersion $appVersion,
        RequestStack $requestStack,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        EventDispatcherInterface $dispatcher,
        CoreParametersHelper $coreParametersHelper,
        MauticFactory $factory,
        protected ContactMerger $contactMerger,
        protected LeadModel $leadModel,
    ) {
        parent::__construct($security, $translator, $entityResultHelper, $router, $formFactory, $appVersion, $requestStack, $doctrine, $modelFactory, $dispatcher, $coreParametersHelper, $factory);
    }

    public function mergeContactsAction(Request $request, $id) {
        if (!$this->security->isGranted(
            [
                'lead:leads:editother'
            ],
            'MATCH_ONE'
        )) {
            return $this->accessDenied();
        }

        // Load the winner contact entity.
        $winner = $this->leadModel->getEntity($id);

        // Get the form to validate payload data.
        $form = $this->getForm();
        $form->submit(json_decode($request->getContent(), true));

        if (!$form->isValid()) {
            $errors = [];
            /** @var FormError $error */
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->handleView(
                $this->view(['errors' => $errors], Response::HTTP_BAD_REQUEST)
            );
        }

        $form_data = $form->getData();

        foreach ($form_data['contact_ids'] as $contact_id) {
            $loser = $this->leadModel->getEntity($contact_id);
            // If lead does not exist, skip it.
            if (!$loser instanceof Lead) {
                continue;
            }

            // Skip if they are the same contact or have been previously merged.
            if ($winner->getId() === $loser->getId()) {
                continue;
            }

            // Skip merging previously identified losers unless explicitly allowed.
            if (!$loser->isAnonymous() && !$form_data['allow_identified']) {
                continue;
            }

            $this->contactMerger->merge($winner, $loser);

        }

        $view = $this->view($winner, Response::HTTP_CREATED);
        return $this->handleView($view);
    }

    private function getForm() {
        return $this->formFactory
            ->createNamedBuilder('', FormType::class, null, [
                'csrf_protection' => false,
                'allow_extra_fields' => false, // disallow any fields other than contact_ids
            ])
            ->add('contact_ids', CollectionType::class, [
                'entry_type'    => IntegerType::class,
                'allow_add'     => true,
                'required'      => true,
                'constraints'   => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'At least one contact ID is required.',
                    ]),
                ],
            ])
            ->add('allow_identified', BooleanType::class, [
                'required' => false,
            ])
            ->getForm();
    }
}