--
-- PostgreSQL database dump
--

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
-- PostgreSQL database dump complete
--


