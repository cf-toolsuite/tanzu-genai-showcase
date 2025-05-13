<?php

namespace App\Form;

use App\Entity\ExecutiveProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExecutiveProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Full Name',
                'attr' => ['placeholder' => 'Enter the executive\'s full name']
            ])
            ->add('title', TextType::class, [
                'label' => 'Job Title',
                'attr' => ['placeholder' => 'Enter the executive\'s job title (e.g. CEO, CFO)']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'required' => false,
                'attr' => ['placeholder' => 'Enter the executive\'s email address']
            ])
            ->add('biography', TextareaType::class, [
                'label' => 'Biography',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter a detailed biography of the executive',
                    'rows' => 6
                ]
            ])
            ->add('education', TextareaType::class, [
                'label' => 'Education History',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter education details (schools, degrees, years)',
                    'rows' => 3
                ]
            ])
            ->add('previousCompanies', TextareaType::class, [
                'label' => 'Previous Companies',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter previous companies and roles',
                    'rows' => 3
                ]
            ])
            ->add('linkedinProfileUrl', UrlType::class, [
                'label' => 'LinkedIn Profile URL',
                'required' => false,
                'attr' => ['placeholder' => 'https://www.linkedin.com/in/username']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExecutiveProfile::class,
        ]);
    }
}
