<?php

namespace Persona\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class RelationshipLinkedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Model $relationship,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $other = $this->determineOtherSide($notifiable);

        return [
            'relationship_id' => $this->relationship->id,
            'type' => $this->relationship->type,
            'other_personable_type' => $other['type'],
            'other_personable_id' => $other['id'],
        ];
    }

    /**
     * Identify the party on the opposite side of the notifiable.
     *
     * Only the morph class and primary key of the other party are exposed;
     * no sensitive profile data leaves the package.
     *
     * @return array{type: string, id: int|string}
     */
    protected function determineOtherSide(object $notifiable): array
    {
        $targetsNotifiable = method_exists($notifiable, 'getMorphClass')
            && $notifiable->getMorphClass() === $this->relationship->related_personable_type
            && (string) $notifiable->getKey() === (string) $this->relationship->related_personable_id;

        if ($targetsNotifiable) {
            return [
                'type' => $this->relationship->personable_type,
                'id' => $this->relationship->personable_id,
            ];
        }

        return [
            'type' => $this->relationship->related_personable_type,
            'id' => $this->relationship->related_personable_id,
        ];
    }
}