<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Responder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ResponderAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Message de réponse',
                'required' => true,
                'attr' => ['rows' => 8, 'class' => 'form-control'],
            ])
            ->add('fromDate', DateTimeType::class, [
                'label' => 'Actif à partir du',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                // Même fuseau modèle + vue : l’heure saisie = heure enregistrée (Europe/Paris).
                'model_timezone' => 'Europe/Paris',
                'view_timezone' => 'Europe/Paris',
                'help' => 'Heure d’Europe/Paris (fuseau du serveur d’application).',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('toDate', DateTimeType::class, [
                'label' => "Actif jusqu'au",
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'model_timezone' => 'Europe/Paris',
                'view_timezone' => 'Europe/Paris',
                'help' => 'Heure d’Europe/Paris (fuseau du serveur d’application).',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Responder::class,
        ]);
    }
}
