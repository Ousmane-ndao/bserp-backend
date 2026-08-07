public function toArray($request)
{
    return [
        'id' => $this->id,
        'email' => $this->email,
        'email_password' => $this->email_password,
        'campus_password' => $this->campus_password,
        'parcoursup_password' => $this->parcoursup_password,
        // ... autres champs
    ];
}