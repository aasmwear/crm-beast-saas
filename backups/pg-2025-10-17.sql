--
-- PostgreSQL database dump
--

\restrict FMxWJuB66NqFiMn26hIl3mlhaBWEqoCW6v4xy3VETCHqGYar3oekqKal9Z46FFo

-- Dumped from database version 17.6
-- Dumped by pg_dump version 17.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: announcements; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.announcements (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    author_id bigint NOT NULL,
    scope character varying(255) DEFAULT 'company'::character varying NOT NULL,
    targets json,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    pinned boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.announcements OWNER TO sail;

--
-- Name: announcements_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.announcements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.announcements_id_seq OWNER TO sail;

--
-- Name: announcements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.announcements_id_seq OWNED BY public.announcements.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.audit_logs (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    actor_id bigint,
    action character varying(255) NOT NULL,
    entity character varying(255) NOT NULL,
    entity_id bigint NOT NULL,
    changes json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.audit_logs OWNER TO sail;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.audit_logs_id_seq OWNER TO sail;

--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO sail;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO sail;

--
-- Name: clients; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.clients (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    company_name character varying(255) NOT NULL,
    industry character varying(255),
    niche character varying(255),
    primary_contact_name character varying(255),
    primary_contact_email character varying(255),
    primary_contact_phone character varying(255),
    website character varying(255),
    address text,
    tags json,
    fronter json,
    closer json,
    assigned_account_manager_id bigint,
    google_business_profile_status character varying(255) DEFAULT 'Not Created'::character varying NOT NULL,
    google_business_profile_access_status character varying(255) DEFAULT 'No Access'::character varying NOT NULL,
    client_activation_status character varying(255) DEFAULT 'Inactive'::character varying NOT NULL,
    notes_by_cst text,
    notes_by_sales text,
    notes_by_tech text,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    search_vector text
);


ALTER TABLE public.clients OWNER TO sail;

--
-- Name: clients_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.clients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.clients_id_seq OWNER TO sail;

--
-- Name: clients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.clients_id_seq OWNED BY public.clients.id;


--
-- Name: departments; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.departments (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.departments OWNER TO sail;

--
-- Name: departments_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.departments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.departments_id_seq OWNER TO sail;

--
-- Name: departments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.departments_id_seq OWNED BY public.departments.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO sail;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO sail;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO sail;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO sail;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO sail;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO sail;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO sail;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    team_id bigint NOT NULL
);


ALTER TABLE public.model_has_permissions OWNER TO sail;

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    team_id bigint NOT NULL
);


ALTER TABLE public.model_has_roles OWNER TO sail;

--
-- Name: notifications_center; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.notifications_center (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    actor_id bigint,
    recipient_ids json,
    entity character varying(255),
    entity_id bigint,
    payload json,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.notifications_center OWNER TO sail;

--
-- Name: notifications_center_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.notifications_center_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_center_id_seq OWNER TO sail;

--
-- Name: notifications_center_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.notifications_center_id_seq OWNED BY public.notifications_center.id;


--
-- Name: organization_domains; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.organization_domains (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    domain character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organization_domains OWNER TO sail;

--
-- Name: organization_domains_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.organization_domains_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_domains_id_seq OWNER TO sail;

--
-- Name: organization_domains_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.organization_domains_id_seq OWNED BY public.organization_domains.id;


--
-- Name: organization_user; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.organization_user (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    user_id bigint NOT NULL,
    is_owner boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organization_user OWNER TO sail;

--
-- Name: organization_user_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.organization_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_user_id_seq OWNER TO sail;

--
-- Name: organization_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.organization_user_id_seq OWNED BY public.organization_user.id;


--
-- Name: organizations; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.organizations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    plan character varying(255) DEFAULT 'trial'::character varying NOT NULL,
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organizations OWNER TO sail;

--
-- Name: organizations_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.organizations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organizations_id_seq OWNER TO sail;

--
-- Name: organizations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.organizations_id_seq OWNED BY public.organizations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO sail;

--
-- Name: permissions; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO sail;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO sail;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: project_messages; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.project_messages (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    project_id bigint NOT NULL,
    author_id bigint NOT NULL,
    parent_id bigint,
    body text NOT NULL,
    attachments json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.project_messages OWNER TO sail;

--
-- Name: project_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.project_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.project_messages_id_seq OWNER TO sail;

--
-- Name: project_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.project_messages_id_seq OWNED BY public.project_messages.id;


--
-- Name: projects; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.projects (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    client_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    project_code character varying(255),
    description text,
    project_manager_id bigint NOT NULL,
    department_id bigint,
    start_date date,
    end_date date,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    budget numeric(12,2),
    price numeric(12,2),
    billable boolean DEFAULT true NOT NULL,
    google_business_profile_status character varying(255),
    google_business_profile_access_status character varying(255),
    client_activation_status character varying(255),
    notes_by_cst text,
    notes_by_sales text,
    notes_by_tech text,
    attachments json,
    custom_fields json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.projects OWNER TO sail;

--
-- Name: projects_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.projects_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.projects_id_seq OWNER TO sail;

--
-- Name: projects_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.projects_id_seq OWNED BY public.projects.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO sail;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    team_id bigint,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO sail;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO sail;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO sail;

--
-- Name: settings; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.settings OWNER TO sail;

--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settings_id_seq OWNER TO sail;

--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: tasks; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.tasks (
    id bigint NOT NULL,
    organization_id bigint NOT NULL,
    project_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    assignees json,
    due_date date,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    estimated_hours numeric(8,2),
    logged_hours numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    subtasks json,
    attachments json,
    comments json,
    submission json,
    review_status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.tasks OWNER TO sail;

--
-- Name: tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tasks_id_seq OWNER TO sail;

--
-- Name: tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.tasks_id_seq OWNED BY public.tasks.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: sail
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    active_organization_id bigint,
    designation character varying(255),
    timezone character varying(255),
    manager_id bigint,
    department_id bigint
);


ALTER TABLE public.users OWNER TO sail;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: sail
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO sail;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: sail
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: announcements id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.announcements ALTER COLUMN id SET DEFAULT nextval('public.announcements_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: clients id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.clients ALTER COLUMN id SET DEFAULT nextval('public.clients_id_seq'::regclass);


--
-- Name: departments id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.departments ALTER COLUMN id SET DEFAULT nextval('public.departments_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: notifications_center id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.notifications_center ALTER COLUMN id SET DEFAULT nextval('public.notifications_center_id_seq'::regclass);


--
-- Name: organization_domains id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_domains ALTER COLUMN id SET DEFAULT nextval('public.organization_domains_id_seq'::regclass);


--
-- Name: organization_user id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_user ALTER COLUMN id SET DEFAULT nextval('public.organization_user_id_seq'::regclass);


--
-- Name: organizations id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organizations ALTER COLUMN id SET DEFAULT nextval('public.organizations_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: project_messages id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages ALTER COLUMN id SET DEFAULT nextval('public.project_messages_id_seq'::regclass);


--
-- Name: projects id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects ALTER COLUMN id SET DEFAULT nextval('public.projects_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: tasks id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.tasks ALTER COLUMN id SET DEFAULT nextval('public.tasks_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: announcements; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.announcements (id, organization_id, author_id, scope, targets, title, body, pinned, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: audit_logs; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.audit_logs (id, organization_id, actor_id, action, entity, entity_id, changes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: clients; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.clients (id, organization_id, company_name, industry, niche, primary_contact_name, primary_contact_email, primary_contact_phone, website, address, tags, fronter, closer, assigned_account_manager_id, google_business_profile_status, google_business_profile_access_status, client_activation_status, notes_by_cst, notes_by_sales, notes_by_tech, status, created_at, updated_at, deleted_at, search_vector) FROM stdin;
5	1	Stark Plumbing	\N	Plumbing	\N	\N	\N	\N	\N	\N	[]	[]	1	Not Created	No Access	Active	\N	\N	\N	active	2025-10-12 11:57:31	2025-10-12 11:57:31	\N	\N
6	1	Wayne Painting	\N	Painting	\N	\N	\N	\N	\N	\N	[]	[]	2	Not Created	No Access	On Hold	\N	\N	\N	active	2025-10-12 11:57:31	2025-10-12 11:57:31	\N	\N
7	1	Stark Plumbing	\N	Plumbing	\N	\N	\N	\N	\N	\N	[]	[]	1	Not Created	No Access	Active	\N	\N	\N	active	2025-10-12 14:28:59	2025-10-12 14:28:59	\N	\N
8	1	Wayne Painting	\N	Painting	\N	\N	\N	\N	\N	\N	[]	[]	2	Not Created	No Access	On Hold	\N	\N	\N	active	2025-10-12 14:28:59	2025-10-12 14:28:59	\N	\N
9	1	Northwind Labs	\N	Marketing	Contact 1	contact1@example.com	\N	\N	\N	\N	\N	\N	\N	Not Created	No Access	Inactive	\N	\N	\N	Active	2025-10-13 20:01:33	2025-10-13 20:01:33	\N	\N
10	1	Blue Horizon	\N	Marketing	Contact 2	contact2@example.com	\N	\N	\N	\N	\N	\N	\N	Not Created	No Access	Inactive	\N	\N	\N	Active	2025-10-13 20:01:33	2025-10-13 20:01:33	\N	\N
1	1	Acme Global Media	Marketing	Lead Gen	Alex Green	alex@acme-client.com	+1-555-0101	https://example.com	101 Market St	["vip","retainer"]	[3]	[4]	1	Created	Access Granted	Active	<p>Inspection complete.</p>	<p>High intent.</p>	<p>GMB clean-up pending.</p>	Active	2025-10-13 20:57:28	2025-10-13 20:57:28	\N	\N
2	1	Acme Studio	Design	Branding	Brooke Sun	brooke@acme-studio.com	+1-555-0202	https://studio.example.com	202 Pine Ave	["one-off"]	[3]	[4]	1	Pending	Access Pending	Active	\N	\N	\N	Active	2025-10-13 20:57:28	2025-10-13 20:57:28	\N	\N
3	2	Beta Global Media	Marketing	Lead Gen	Alex Green	alex@beta-client.com	+1-555-0101	https://example.com	101 Market St	["vip","retainer"]	[7]	[8]	5	Created	Access Granted	Active	<p>Inspection complete.</p>	<p>High intent.</p>	<p>GMB clean-up pending.</p>	Active	2025-10-13 20:57:28	2025-10-13 20:57:28	\N	\N
4	2	Beta Studio	Design	Branding	Brooke Sun	brooke@beta-studio.com	+1-555-0202	https://studio.example.com	202 Pine Ave	["one-off"]	[7]	[8]	5	Pending	Access Pending	Active	\N	\N	\N	Active	2025-10-13 20:57:28	2025-10-13 20:57:28	\N	\N
\.


--
-- Data for Name: departments; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.departments (id, organization_id, name, code, created_at, updated_at) FROM stdin;
1	1	Customer Success & Tech	CST	2025-10-13 20:57:28	2025-10-13 20:57:28
2	1	Sales	SAL	2025-10-13 20:57:28	2025-10-13 20:57:28
3	1	Creative	CR	2025-10-13 20:57:28	2025-10-13 20:57:28
4	2	Customer Success & Tech	CST	2025-10-13 20:57:28	2025-10-13 20:57:28
5	2	Sales	SAL	2025-10-13 20:57:28	2025-10-13 20:57:28
6	2	Creative	CR	2025-10-13 20:57:28	2025-10-13 20:57:28
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_10_09_014126_create_permission_tables	1
5	2025_10_09_100000_create_tenancy_tables	1
6	2025_10_09_110000_create_core_entities	1
7	2025_10_12_020000_add_department_id_to_users_table	2
8	2025_10_12_140000_update_clients_for_visibility_and_indexes	3
9	2025_10_12_150000_fix_clients_columns	4
10	2025_10_14_120000_align_clients_table	5
11	2025_10_16_000001_add_deleted_at_to_clients_table	6
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.model_has_permissions (permission_id, model_type, model_id, team_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.model_has_roles (role_id, model_type, model_id, team_id) FROM stdin;
1	App\\Models\\User	1	1
2	App\\Models\\User	2	1
3	App\\Models\\User	3	1
3	App\\Models\\User	4	1
1	App\\Models\\User	5	2
2	App\\Models\\User	6	2
3	App\\Models\\User	7	2
3	App\\Models\\User	8	2
\.


--
-- Data for Name: notifications_center; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.notifications_center (id, organization_id, type, actor_id, recipient_ids, entity, entity_id, payload, read_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: organization_domains; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.organization_domains (id, organization_id, domain, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: organization_user; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.organization_user (id, organization_id, user_id, is_owner, created_at, updated_at) FROM stdin;
1	1	1	f	2025-10-13 20:57:28	2025-10-13 20:57:28
2	1	2	f	2025-10-13 20:57:28	2025-10-13 20:57:28
3	1	3	f	2025-10-13 20:57:28	2025-10-13 20:57:28
4	1	4	f	2025-10-13 20:57:28	2025-10-13 20:57:28
5	2	5	f	2025-10-13 20:57:28	2025-10-13 20:57:28
6	2	6	f	2025-10-13 20:57:28	2025-10-13 20:57:28
7	2	7	f	2025-10-13 20:57:28	2025-10-13 20:57:28
8	2	8	f	2025-10-13 20:57:28	2025-10-13 20:57:28
\.


--
-- Data for Name: organizations; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.organizations (id, name, slug, plan, settings, created_at, updated_at) FROM stdin;
1	Acme	acme	trial	[]	2025-10-12 01:32:57	2025-10-12 01:32:57
2	Beta	beta	trial	[]	2025-10-12 01:32:57	2025-10-12 01:32:57
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: project_messages; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.project_messages (id, organization_id, project_id, author_id, parent_id, body, attachments, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.projects (id, organization_id, client_id, title, project_code, description, project_manager_id, department_id, start_date, end_date, status, budget, price, billable, google_business_profile_status, google_business_profile_access_status, client_activation_status, notes_by_cst, notes_by_sales, notes_by_tech, attachments, custom_fields, created_at, updated_at, deleted_at) FROM stdin;
5	1	1	Website Revamp	\N	\N	2	\N	\N	\N	Active	\N	\N	t	\N	\N	\N	\N	\N	\N	\N	\N	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
6	1	1	Website Revamp	\N	\N	2	\N	\N	\N	Active	\N	\N	t	\N	\N	\N	\N	\N	\N	\N	\N	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
7	1	1	Website Revamp	\N	\N	2	\N	\N	\N	Active	\N	\N	t	\N	\N	\N	\N	\N	\N	\N	\N	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
8	1	1	Website Revamp	\N	\N	2	\N	\N	\N	Active	\N	\N	t	\N	\N	\N	\N	\N	\N	\N	\N	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
9	1	1	Website Revamp	\N	\N	2	\N	\N	\N	Active	\N	\N	t	\N	\N	\N	\N	\N	\N	\N	\N	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
1	1	1	Local SEO Rollout	ACME-001	GBP optimization + reviews campaign	2	1	2025-10-03	2025-12-13	Active	5000.00	6500.00	t	Created	Access Granted	Active	\N	\N	\N	[]	{"tier":"gold"}	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
2	1	2	Landing Page + Ads	ACME-002	CRO-focused LP + Google Ads	2	1	2025-10-10	2025-11-13	Active	8000.00	9800.00	t	\N	\N	Active	\N	\N	\N	[]	{"tier":"silver"}	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
3	2	3	Local SEO Rollout	BETA-001	GBP optimization + reviews campaign	6	4	2025-10-03	2025-12-13	Active	5000.00	6500.00	t	Created	Access Granted	Active	\N	\N	\N	[]	{"tier":"gold"}	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
4	2	4	Landing Page + Ads	BETA-002	CRO-focused LP + Google Ads	6	4	2025-10-10	2025-11-13	Active	8000.00	9800.00	t	\N	\N	Active	\N	\N	\N	[]	{"tier":"silver"}	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.roles (id, team_id, name, guard_name, created_at, updated_at) FROM stdin;
1	\N	Admin	web	2025-10-12 01:39:18	2025-10-12 01:39:18
2	\N	ProjectManager	web	2025-10-12 01:39:19	2025-10-12 01:39:19
3	\N	Viewer	web	2025-10-12 01:39:19	2025-10-12 01:39:19
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.settings (id, organization_id, key, value, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: tasks; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.tasks (id, organization_id, project_id, title, description, assignees, due_date, priority, status, estimated_hours, logged_hours, subtasks, attachments, comments, submission, review_status, created_at, updated_at, deleted_at) FROM stdin;
7	1	5	Task #1	\N	[1]	2025-10-13	2	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
8	1	5	Task #2	\N	[1]	2025-10-14	5	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
9	1	5	Task #3	\N	[1]	2025-10-15	0	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
10	1	5	Task #4	\N	[1]	2025-10-16	5	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
11	1	5	Task #5	\N	[1]	2025-10-17	5	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
12	1	5	Task #6	\N	[1]	2025-10-18	4	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
13	1	5	Task #7	\N	[1]	2025-10-19	3	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
14	1	5	Task #8	\N	[1]	2025-10-20	2	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 03:03:35	2025-10-12 03:03:35	\N
15	1	6	Task #1	\N	[1]	2025-10-13	3	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
16	1	6	Task #2	\N	[1]	2025-10-14	1	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
17	1	6	Task #3	\N	[1]	2025-10-15	5	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
18	1	6	Task #4	\N	[1]	2025-10-16	1	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
19	1	6	Task #5	\N	[1]	2025-10-17	2	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
20	1	6	Task #6	\N	[1]	2025-10-18	2	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
21	1	6	Task #7	\N	[1]	2025-10-19	0	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
22	1	6	Task #8	\N	[1]	2025-10-20	1	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:06:00	2025-10-12 11:06:00	\N
23	1	7	Task #1	\N	[1]	2025-10-13	5	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
24	1	7	Task #2	\N	[1]	2025-10-14	0	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
25	1	7	Task #3	\N	[1]	2025-10-15	0	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
26	1	7	Task #4	\N	[1]	2025-10-16	1	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
27	1	7	Task #5	\N	[1]	2025-10-17	3	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
28	1	7	Task #6	\N	[1]	2025-10-18	5	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
29	1	7	Task #7	\N	[1]	2025-10-19	0	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
30	1	7	Task #8	\N	[1]	2025-10-20	5	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:22:22	2025-10-12 11:22:22	\N
31	1	8	Task #1	\N	[1]	2025-10-13	1	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
32	1	8	Task #2	\N	[1]	2025-10-14	0	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
33	1	8	Task #3	\N	[1]	2025-10-15	3	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
34	1	8	Task #4	\N	[1]	2025-10-16	2	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
35	1	8	Task #5	\N	[1]	2025-10-17	0	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
36	1	8	Task #6	\N	[1]	2025-10-18	2	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
37	1	8	Task #7	\N	[1]	2025-10-19	2	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
38	1	8	Task #8	\N	[1]	2025-10-20	0	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 11:57:31	2025-10-12 11:57:31	\N
39	1	9	Task #1	\N	[1]	2025-10-13	3	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
40	1	9	Task #2	\N	[1]	2025-10-14	1	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
41	1	9	Task #3	\N	[1]	2025-10-15	2	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
42	1	9	Task #4	\N	[1]	2025-10-16	5	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
43	1	9	Task #5	\N	[1]	2025-10-17	3	In Progress	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
44	1	9	Task #6	\N	[1]	2025-10-18	4	Review	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
45	1	9	Task #7	\N	[1]	2025-10-19	0	Done	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
46	1	9	Task #8	\N	[1]	2025-10-20	5	Backlog	\N	0.00	\N	\N	\N	\N	pending	2025-10-12 14:28:59	2025-10-12 14:28:59	\N
1	1	1	Audit GBP	Auto-seeded task	[2]	2025-10-20	medium	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
2	1	1	Review workflow	Auto-seeded task	[2]	2025-10-21	high	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
3	1	2	LP wireframe	Auto-seeded task	[2]	2025-10-22	high	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
4	2	3	Audit GBP	Auto-seeded task	[6]	2025-10-20	medium	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
5	2	3	Review workflow	Auto-seeded task	[6]	2025-10-21	high	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
6	2	4	LP wireframe	Auto-seeded task	[6]	2025-10-22	high	In Progress	6.00	0.00	[]	[]	\N	\N	Pending	2025-10-13 20:57:28	2025-10-13 20:57:28	\N
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: sail
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, active_organization_id, designation, timezone, manager_id, department_id) FROM stdin;
1	Owner (Acme)	owner@acme.test	\N	$2y$12$mvqHbM3z35rTFJnUJQw8Seoy1EOv6QMABkHJvsnA/GWhsM5y7402m	\N	2025-10-12 01:39:18	2025-10-12 01:39:18	\N	\N	\N	\N	2
2	PM (Acme)	pm@acme.test	\N	$2y$12$.E2h7GWZlUCzv/mgLi3HYesm8pUz7o/4FdnWp3QZsc5i/VjxGIIJC	\N	2025-10-12 01:39:18	2025-10-12 01:39:18	\N	\N	\N	\N	1
3	Sales A (Acme)	salesa@acme.test	\N	$2y$12$wDZ98SVAvLsJEt268qvJRu0arw5H8rnTftCKX14HXyOuCTZXPBzNq	\N	2025-10-12 01:39:18	2025-10-12 01:39:18	\N	\N	\N	\N	2
4	Sales B (Acme)	salesb@acme.test	\N	$2y$12$Nne1s79yWT8RanGA8SfEBux7uMqCR3FCYco.Zuw3RGAP4pVwAh5La	\N	2025-10-12 01:39:18	2025-10-12 01:39:18	\N	\N	\N	\N	2
5	Owner (Beta)	owner@beta.test	\N	$2y$12$PypeRY3uyx6OEMPE75Bejewc2s6/hoZvPUpJMeuJ66fDPYS5zj8Iy	\N	2025-10-12 01:49:22	2025-10-12 01:49:22	\N	\N	\N	\N	5
6	PM (Beta)	pm@beta.test	\N	$2y$12$2k5qKAhNFpEStklwcEGQMO2WXG7wYwi0wYrdDgd74zF1IdeTwctii	\N	2025-10-12 01:49:23	2025-10-12 01:49:23	\N	\N	\N	\N	4
7	Sales A (Beta)	salesa@beta.test	\N	$2y$12$kfddoZob9/NTLdnEvmBvuerT.fZ6JCSCOjTGk7ocCSDVqMB0LdSlm	\N	2025-10-12 01:49:23	2025-10-12 01:49:23	\N	\N	\N	\N	5
8	Sales B (Beta)	salesb@beta.test	\N	$2y$12$yxA4ODvQeGi4aAGsmCS6reDF2fr0hRH9I8Qxvz/eoVaVF0v1oa/Ja	\N	2025-10-12 01:49:23	2025-10-12 01:49:23	\N	\N	\N	\N	5
\.


--
-- Name: announcements_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.announcements_id_seq', 1, false);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.audit_logs_id_seq', 1, false);


--
-- Name: clients_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.clients_id_seq', 10, true);


--
-- Name: departments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.departments_id_seq', 6, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.migrations_id_seq', 11, true);


--
-- Name: notifications_center_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.notifications_center_id_seq', 1, false);


--
-- Name: organization_domains_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.organization_domains_id_seq', 1, false);


--
-- Name: organization_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.organization_user_id_seq', 8, true);


--
-- Name: organizations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.organizations_id_seq', 2, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.permissions_id_seq', 1, false);


--
-- Name: project_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.project_messages_id_seq', 1, false);


--
-- Name: projects_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.projects_id_seq', 9, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.roles_id_seq', 3, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.settings_id_seq', 1, false);


--
-- Name: tasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.tasks_id_seq', 46, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: sail
--

SELECT pg_catalog.setval('public.users_id_seq', 8, true);


--
-- Name: announcements announcements_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (id);


--
-- Name: departments departments_organization_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_organization_id_code_unique UNIQUE (organization_id, code);


--
-- Name: departments departments_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (team_id, permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (team_id, role_id, model_id, model_type);


--
-- Name: notifications_center notifications_center_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.notifications_center
    ADD CONSTRAINT notifications_center_pkey PRIMARY KEY (id);


--
-- Name: organization_domains organization_domains_domain_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_domains
    ADD CONSTRAINT organization_domains_domain_unique UNIQUE (domain);


--
-- Name: organization_domains organization_domains_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_domains
    ADD CONSTRAINT organization_domains_pkey PRIMARY KEY (id);


--
-- Name: organization_user organization_user_organization_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_user
    ADD CONSTRAINT organization_user_organization_id_user_id_unique UNIQUE (organization_id, user_id);


--
-- Name: organization_user organization_user_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_user
    ADD CONSTRAINT organization_user_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_pkey PRIMARY KEY (id);


--
-- Name: organizations organizations_slug_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organizations
    ADD CONSTRAINT organizations_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: project_messages project_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages
    ADD CONSTRAINT project_messages_pkey PRIMARY KEY (id);


--
-- Name: projects projects_organization_id_project_code_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_organization_id_project_code_unique UNIQUE (organization_id, project_code);


--
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_team_id_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_team_id_name_guard_name_unique UNIQUE (team_id, name, guard_name);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_organization_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_organization_id_key_unique UNIQUE (organization_id, key);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: tasks tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: announcements_organization_id_scope_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX announcements_organization_id_scope_index ON public.announcements USING btree (organization_id, scope);


--
-- Name: audit_logs_organization_id_entity_entity_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX audit_logs_organization_id_entity_entity_id_index ON public.audit_logs USING btree (organization_id, entity, entity_id);


--
-- Name: clients_organization_id_assigned_account_manager_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX clients_organization_id_assigned_account_manager_id_index ON public.clients USING btree (organization_id, assigned_account_manager_id);


--
-- Name: clients_organization_id_company_name_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX clients_organization_id_company_name_index ON public.clients USING btree (organization_id, company_name);


--
-- Name: clients_search_vector_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX clients_search_vector_index ON public.clients USING btree (search_vector);


--
-- Name: departments_code_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX departments_code_index ON public.departments USING btree (code);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_permissions_team_foreign_key_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX model_has_permissions_team_foreign_key_index ON public.model_has_permissions USING btree (team_id);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: model_has_roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX model_has_roles_team_foreign_key_index ON public.model_has_roles USING btree (team_id);


--
-- Name: notifications_center_organization_id_type_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX notifications_center_organization_id_type_index ON public.notifications_center USING btree (organization_id, type);


--
-- Name: project_messages_organization_id_project_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX project_messages_organization_id_project_id_index ON public.project_messages USING btree (organization_id, project_id);


--
-- Name: projects_organization_id_client_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX projects_organization_id_client_id_index ON public.projects USING btree (organization_id, client_id);


--
-- Name: roles_team_foreign_key_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX roles_team_foreign_key_index ON public.roles USING btree (team_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: tasks_organization_id_project_id_index; Type: INDEX; Schema: public; Owner: sail
--

CREATE INDEX tasks_organization_id_project_id_index ON public.tasks USING btree (organization_id, project_id);


--
-- Name: announcements announcements_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: announcements announcements_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.announcements
    ADD CONSTRAINT announcements_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: audit_logs audit_logs_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: clients clients_assigned_account_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_assigned_account_manager_id_foreign FOREIGN KEY (assigned_account_manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: clients clients_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: departments departments_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.departments
    ADD CONSTRAINT departments_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: notifications_center notifications_center_actor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.notifications_center
    ADD CONSTRAINT notifications_center_actor_id_foreign FOREIGN KEY (actor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: notifications_center notifications_center_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.notifications_center
    ADD CONSTRAINT notifications_center_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_domains organization_domains_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_domains
    ADD CONSTRAINT organization_domains_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_user organization_user_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_user
    ADD CONSTRAINT organization_user_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: organization_user organization_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.organization_user
    ADD CONSTRAINT organization_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: project_messages project_messages_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages
    ADD CONSTRAINT project_messages_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: project_messages project_messages_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages
    ADD CONSTRAINT project_messages_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: project_messages project_messages_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages
    ADD CONSTRAINT project_messages_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.project_messages(id) ON DELETE SET NULL;


--
-- Name: project_messages project_messages_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.project_messages
    ADD CONSTRAINT project_messages_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: projects projects_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.clients(id) ON DELETE CASCADE;


--
-- Name: projects projects_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: projects projects_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: projects projects_project_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_project_manager_id_foreign FOREIGN KEY (project_manager_id) REFERENCES public.users(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: settings settings_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: tasks tasks_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_organization_id_foreign FOREIGN KEY (organization_id) REFERENCES public.organizations(id) ON DELETE CASCADE;


--
-- Name: tasks tasks_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.projects(id) ON DELETE CASCADE;


--
-- Name: users users_active_organization_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_active_organization_id_foreign FOREIGN KEY (active_organization_id) REFERENCES public.organizations(id) ON DELETE SET NULL;


--
-- Name: users users_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.departments(id) ON DELETE SET NULL;


--
-- Name: users users_manager_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: sail
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_manager_id_foreign FOREIGN KEY (manager_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict FMxWJuB66NqFiMn26hIl3mlhaBWEqoCW6v4xy3VETCHqGYar3oekqKal9Z46FFo

