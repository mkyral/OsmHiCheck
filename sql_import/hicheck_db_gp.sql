--
-- PostgreSQL database dump
--

-- Dumped from database version 9.4.9
-- Dumped by pg_dump version 9.4.9
-- Started on 2016-08-17 14:06:47 CEST

SET client_encoding = 'UTF8';

--
-- Name: gp_analyze; Type: TABLE; Schema: hicheck; Owner: xsvana00; Tablespace: 
--

CREATE TABLE gp_analyze (
    nodeid bigint NOT NULL,
    ref character varying(128),
    geom public.geometry(Point,4326),
    img character varying(512)
);

--
-- Name: gp_stats; Type: TABLE; Schema: hicheck; Owner: xsvana00; Tablespace: 
--

CREATE TABLE gp_stats (
    id integer DEFAULT nextval('stats_id_seq'::regclass) NOT NULL,
    tstamp timestamp without time zone DEFAULT now(),
    date character varying(10),
    node_total integer,
    img_total integer,
    img_used integer,
    node_ok integer,
    node_cor integer,
    node_bad integer
);


--
-- Name: guideposts; Type: TABLE; Schema: hicheck; Owner: xsvana00; Tablespace: 
--

CREATE TABLE guideposts (
    id bigint NOT NULL,
    url character varying(128) NOT NULL,
    ref character varying(128),
    geom public.geometry(Point,4326),
    by character varying(64)
);

COMMENT ON COLUMN guideposts.by IS 'author of the photo';


--
-- Name: gp_stats_pkey; Type: CONSTRAINT; Schema: hicheck; Owner: xsvana00; Tablespace: 
--

ALTER TABLE ONLY gp_stats
    ADD CONSTRAINT gp_stats_pkey PRIMARY KEY (id);

--
-- Name: guideposts_pkey; Type: CONSTRAINT; Schema: hicheck; Owner: xsvana00; Tablespace: 
--

ALTER TABLE ONLY guideposts
    ADD CONSTRAINT guideposts_pkey PRIMARY KEY (id);

CREATE INDEX guideposts_geom_idx ON guideposts USING gist (geom);

-- Completed on 2016-08-17 14:06:48 CEST

--
-- PostgreSQL database dump complete
--

