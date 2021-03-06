<?php
namespace Controller\Admin;

use App\Flash;
use Doctrine\ORM\EntityManager;
use Entity;
use App\Http\Request;
use App\Http\Response;

class PermissionsController
{
    /** @var EntityManager */
    protected $em;

    /** @var Flash */
    protected $flash;

    /** @var array */
    protected $form_config;

    /**
     * @param EntityManager $em
     * @param Flash $flash
     * @param array $form_config
     */
    public function __construct(EntityManager $em, Flash $flash, array $form_config)
    {
        $this->em = $em;
        $this->flash = $flash;
        $this->form_config = $form_config;
    }

    public function indexAction(Request $request, Response $response): Response
    {
        $all_roles = $this->em->createQuery('SELECT r, rp, s FROM Entity\Role r 
            LEFT JOIN r.users u LEFT JOIN r.permissions rp LEFT JOIN rp.station s 
            ORDER BY r.id ASC')
            ->getArrayResult();

        $roles = [];

        foreach ($all_roles as $role) {
            $role['permissions_global'] = [];
            $role['permissions_station'] = [];

            foreach ($role['permissions'] as $permission) {
                if ($permission['station']) {
                    $role['permissions_station'][$permission['station']['name']][] = $permission['action_name'];
                } else {
                    $role['permissions_global'][] = $permission['action_name'];
                }
            }

            $roles[] = $role;
        }

        /** @var \App\Mvc\View $view */
        $view = $request->getAttribute('view');

        return $view->renderToResponse($response, 'admin/permissions/index', [
            'roles' => $roles,
        ]);
    }

    public function editAction(Request $request, Response $response, $id = null): Response
    {
        /** @var Entity\Repository\BaseRepository $role_repo */
        $role_repo = $this->em->getRepository(Entity\Role::class);

        /** @var Entity\Repository\RolePermissionRepository $permission_repo */
        $permission_repo = $this->em->getRepository(Entity\RolePermission::class);

        $form = new \App\Form($this->form_config);

        if (!empty($id)) {
            $record = $role_repo->find($id);
            $record_info = $role_repo->toArray($record, true, true);

            $actions = $permission_repo->getActionsForRole($record);

            $form->setDefaults(array_merge($record_info, $actions));
        } else {
            $record = null;
        }

        if (!empty($_POST) && $form->isValid($_POST)) {
            $data = $form->getValues();

            if (!($record instanceof Entity\Role)) {
                $record = new Entity\Role;
            }

            $role_repo->fromArray($record, $data);

            $this->em->persist($record);
            $this->em->flush();

            $permission_repo->setActionsForRole($record, $data);

            $this->flash->alert('<b>' . _('Record updated.') . '</b>', 'green');

            return $response->redirectToRoute('admin:permissions:index');
        }

        /** @var \App\Mvc\View $view */
        $view = $request->getAttribute('view');

        return $view->renderToResponse($response, 'system/form_page', [
            'form' => $form,
            'render_mode' => 'edit',
            'title' => _('Edit Record')
        ]);
    }

    public function deleteAction(Request $request, Response $response, $id): Response
    {
        /** @var Entity\Repository\BaseRepository $role_repo */
        $role_repo = $this->em->getRepository(Entity\Role::class);

        $record = $role_repo->find((int)$id);
        if ($record instanceof Entity\Role) {
            $this->em->remove($record);
        }

        $this->em->flush();

        $this->flash->alert('<b>' . _('Record deleted.') . '</b>', 'green');
        return $response->redirectToRoute('admin:permissions:index');
    }
}