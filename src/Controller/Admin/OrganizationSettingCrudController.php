<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\OrganizationSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class OrganizationSettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OrganizationSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Coordonnées agence (répondeurs)')
            ->setEntityLabelInPlural('Coordonnées agence')
            ->setPageTitle(Crud::PAGE_INDEX, 'Téléphone agence pour les modèles de répondeur')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier les coordonnées agence')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('agencyPhone', 'Téléphone de l’agence')
            ->setHelp('Inséré dans les messages via la variable {telephone_agence}.');
    }
}
