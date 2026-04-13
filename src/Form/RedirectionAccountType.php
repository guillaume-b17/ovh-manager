<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Redirection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RedirectionAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('toEmail', EmailType::class, [
            'label' => 'Rediriger vers',
            'required' => true,
            'attr' => ['class' => 'form-control', 'autocomplete' => 'email'],
            'help' => 'Adresse e-mail qui recevra les messages.',
        ]);

        if ($options['allow_local_copy']) {
            $builder->add('localCopy', CheckboxType::class, [
                'label' => 'Conserver une copie dans cette boîte',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Redirection::class,
            'allow_local_copy' => true,
        ]);
        $resolver->setAllowedTypes('allow_local_copy', 'bool');
    }
}
