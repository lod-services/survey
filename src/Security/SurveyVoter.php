<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SurveyVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const CREATE = 'create';
    public const MANAGE = 'manage';
    public const RESPOND = 'respond';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Replace with your own logic to determine if the voter should handle this
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE, self::MANAGE, self::RESPOND])
            && ($subject === 'survey' || is_object($subject)); // In real implementation, check if $subject is Survey entity
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        // Check permissions based on the attribute
        switch ($attribute) {
            case self::CREATE:
                return $this->canCreate($user);
            case self::VIEW:
                return $this->canView($user, $subject);
            case self::EDIT:
                return $this->canEdit($user, $subject);
            case self::DELETE:
                return $this->canDelete($user, $subject);
            case self::MANAGE:
                return $this->canManage($user, $subject);
            case self::RESPOND:
                return $this->canRespond($user, $subject);
        }

        return false;
    }

    private function canCreate(User $user): bool
    {
        // Survey creators and admins can create surveys
        return $user->canCreateSurveys();
    }

    private function canView(User $user, mixed $survey): bool
    {
        // For now, all authenticated users can view surveys
        // In real implementation, you'd check if the survey is public or if user has access
        return true;
    }

    private function canEdit(User $user, mixed $survey): bool
    {
        // Admins can edit any survey
        if ($user->isAdmin()) {
            return true;
        }

        // Survey creators can edit their own surveys
        if ($user->canCreateSurveys()) {
            // In real implementation, check if user owns the survey
            // return $survey->getOwner() === $user;
            return true; // Simplified for now
        }

        return false;
    }

    private function canDelete(User $user, mixed $survey): bool
    {
        // Admins can delete any survey
        if ($user->isAdmin()) {
            return true;
        }

        // Survey creators can delete their own surveys
        if ($user->canCreateSurveys()) {
            // In real implementation, check if user owns the survey
            // return $survey->getOwner() === $user;
            return true; // Simplified for now
        }

        return false;
    }

    private function canManage(User $user, mixed $survey): bool
    {
        // Only admins and survey owners can manage surveys
        return $this->canEdit($user, $survey);
    }

    private function canRespond(User $user, mixed $survey): bool
    {
        // All authenticated users can respond to surveys by default
        // In real implementation, you might check survey settings, user permissions, etc.
        return $user->canRespondToSurveys();
    }
}