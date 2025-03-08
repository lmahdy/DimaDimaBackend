<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250124175826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14B03A8386');
        $this->addSql('DROP INDEX IDX_CFBDFA14B03A8386 ON note');
        $this->addSql('ALTER TABLE note ADD owner_id INT NOT NULL, DROP created_by_id');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA147E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_CFBDFA147E3C61F9 ON note (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA147E3C61F9');
        $this->addSql('DROP INDEX IDX_CFBDFA147E3C61F9 ON note');
        $this->addSql('ALTER TABLE note ADD created_by_id INT DEFAULT NULL, DROP owner_id');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_CFBDFA14B03A8386 ON note (created_by_id)');
    }
}
