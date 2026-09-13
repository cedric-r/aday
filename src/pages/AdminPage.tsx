import { useState } from 'react';
import Box from '@mui/material/Box';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import { UserTable } from '@/components/admin/UserTable';
import { UserModal } from '@/components/admin/UserModal';
import { EventDatePanel } from '@/components/admin/EventDatePanel';
import { SubmissionsPanel } from '@/components/admin/SubmissionsPanel';
import { EmbedPanel } from '@/components/admin/EmbedPanel';
import { EmailPanel } from '@/components/admin/EmailPanel';
import { MessagePanel } from '@/components/admin/MessagePanel';
import type { AdminUser } from '@/schemas/admin.schema';

type TabId = 0 | 1 | 2 | 3 | 4 | 5;

export const AdminPage = () => {
  const [tab, setTab] = useState<TabId>(0);
  const [editUser, setEditUser] = useState<AdminUser | null>(null);
  const [showAddModal, setShowAddModal] = useState(false);
  const [refreshKey, setRefreshKey] = useState(0);

  const handleSaved = () => setRefreshKey((k) => k + 1);

  return (
    <Box component="main" sx={{ maxWidth: 1100, mx: 'auto', py: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Admin
      </Typography>

      <Tabs
        value={tab}
        onChange={(_, v: TabId) => setTab(v)}
        variant="scrollable"
        scrollButtons="auto"
        allowScrollButtonsMobile
        sx={{ mb: 3 }}
      >
        <Tab label="Users" />
        <Tab label="Event Date" />
        <Tab label="Submissions" />
        <Tab label="Embed" />
        <Tab label="Email" />
        <Tab label="Messages" />
      </Tabs>

      {tab === 0 && (
        <Box>
          <Button
            variant="contained"
            sx={{ mb: 2 }}
            onClick={() => setShowAddModal(true)}
          >
            Add user
          </Button>
          <UserTable key={refreshKey} onEdit={(u) => setEditUser(u)} />
        </Box>
      )}

      {tab === 1 && <EventDatePanel />}
      {tab === 2 && <SubmissionsPanel />}
      {tab === 3 && <EmbedPanel />}
      {tab === 4 && <EmailPanel />}
      {tab === 5 && <MessagePanel />}

      {showAddModal && (
        <UserModal
          mode="add"
          onClose={() => setShowAddModal(false)}
          onSaved={handleSaved}
        />
      )}

      {editUser && (
        <UserModal
          mode="edit"
          user={editUser}
          onClose={() => setEditUser(null)}
          onSaved={handleSaved}
        />
      )}
    </Box>
  );
};
